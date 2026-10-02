<?php

namespace App\Actions\Contractor;

use App\Actions\Identity\SecurityAudit;
use App\Actions\Notifications\BusinessNotifications;
use App\Models\AccountState;
use App\Models\ContractorDocument;
use App\Models\ContractorNote;
use App\Models\PrivateUpload;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ContractorManagement
{
    /** @var list<string> */
    private const PROFILE_FIELDS = [
        'company_name', 'company_address', 'company_phone', 'first_name', 'last_name',
        'phone1', 'phone1_type', 'phone2', 'phone2_type', 'email',
    ];

    public function create(User $actor, array $data, ?UploadedFile $photo = null): User
    {
        $createdPaths = [];

        try {
            return DB::transaction(function () use ($actor, $data, $photo, &$createdPaths): User {
                $contractor = new User;
                $contractor->forceFill([
                    'company_name' => $data['company_name'],
                    'company_address' => $data['company_address'],
                    'company_phone' => $data['company_phone'] ?? null,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone1' => $data['phone1'] ?? null,
                    'phone1_type' => $data['phone1_type'],
                    'phone2' => $data['phone2'],
                    'phone2_type' => $data['phone2_type'],
                    'email' => $data['email'] ?? null,
                    'password_hash' => Hash::make($data['password'] ?? Str::random(64)),
                    'is_admin' => 0,
                    'is_approved' => 0,
                    'approved_date' => null,
                    'priority' => (int) ($data['priority'] ?? 0),
                    'created_at' => now(),
                ]);
                $contractor->save();

                $state = new AccountState;
                $state->forceFill([
                    'user_id' => $contractor->user_id,
                    'registration_signals' => ['created_by_admin' => (int) $actor->user_id],
                    'session_version' => 0,
                ])->save();

                $this->syncServices($contractor, $data['services'] ?? []);
                if ($photo !== null) {
                    $upload = $this->storePrivateUpload($contractor, $photo, 'profile_photo', 'profile_photo', null, $createdPaths);
                    $contractor->forceFill(['profile_photo' => $photo->get()])->save();
                    $upload->save();
                }
                app(SecurityAudit::class)->record($actor, 'created contractor account.', $contractor);

                return $contractor->fresh(['accountState', 'services', 'profileUpload']);
            });
        } catch (Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }
    }

    public function update(User $actor, User $subject, array $data, ?UploadedFile $photo = null): User
    {
        $createdPaths = [];

        try {
            return DB::transaction(function () use ($actor, $subject, $data, $photo, &$createdPaths): User {
                $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
                $state = $locked->accountState()->lockForUpdate()->firstOrCreate([], ['session_version' => 0]);
                $beforeServices = $locked->services()->pluck('Services.service_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                $beforeEmail = $locked->email;

                $attributes = [];
                foreach (self::PROFILE_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $attributes[$field] = $data[$field];
                    }
                }
                $profileAttributesChanged = false;
                if ($attributes !== []) {
                    $locked->forceFill($attributes);
                    $profileAttributesChanged = $locked->isDirty(self::PROFILE_FIELDS);
                    $locked->save();
                }
                if ($actor->role() === 'admin' && array_key_exists('priority', $data)) {
                    $locked->forceFill(['priority' => $data['priority'] ? 1 : 0])->save();
                }

                $afterServices = $beforeServices;
                if (array_key_exists('services', $data)) {
                    $this->syncServices($locked, $data['services']);
                    $afterServices = $locked->services()->pluck('Services.service_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                }

                $profileChanged = $profileAttributesChanged || $beforeServices !== $afterServices || $photo !== null;
                $emailChanged = $beforeEmail !== $locked->email;
                if ($emailChanged) {
                    $state->forceFill(['email_verified_at' => null, 'verified_email' => null]);
                }

                if ($photo !== null) {
                    $upload = $this->storePrivateUpload($locked, $photo, 'profile_photo', 'profile_photo', null, $createdPaths);
                    $locked->forceFill(['profile_photo' => $photo->get()])->save();
                    // Keep the database mapping to the private copy; the legacy BLOB remains the import-compatible source.
                    $upload->save();
                }

                if ($locked->role() === 'contractor' && $profileChanged) {
                    $reviewChanged = (int) $locked->is_approved !== 2;
                    $locked->forceFill(['is_approved' => 2, 'approved_date' => null])->save();
                    if ($reviewChanged || $emailChanged) {
                        $state->session_version = ((int) $state->session_version) + 1;
                    }
                } elseif ($emailChanged) {
                    $state->session_version = ((int) $state->session_version) + 1;
                }
                $state->save();

                app(SecurityAudit::class)->record($actor, 'updated contractor profile and services.', $locked);
                if ($profileChanged) {
                    app(BusinessNotifications::class)->contractorProfileChanged($actor, $locked);
                }

                return $locked->fresh(['accountState', 'services', 'profileUpload']);
            });
        } catch (Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }
    }

    public function setApproval(User $actor, User $subject, int $approval, ?string $reason = null): User
    {
        return DB::transaction(function () use ($actor, $subject, $approval, $reason): User {
            $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            abort_if($locked->role() !== 'contractor', 422, 'Only contractor accounts have approval states.');
            $state = $locked->accountState()->lockForUpdate()->firstOrCreate([], ['session_version' => 0]);
            $changed = (int) $locked->is_approved !== $approval;
            $locked->forceFill([
                'is_approved' => $approval,
                'approved_date' => $approval === 1 ? ($changed ? now() : $locked->approved_date) : null,
            ])->save();
            if ($changed) {
                $state->session_version = ((int) $state->session_version) + 1;
            }
            $state->save();
            $suffix = $reason ? ' Reason: '.trim($reason) : '';
            app(SecurityAudit::class)->record($actor, 'changed contractor approval to '.$approval.'.'.$suffix, $locked);
            if ($changed) {
                app(BusinessNotifications::class)->contractorApprovalChanged($actor, $locked);
            }

            return $locked->fresh(['accountState']);
        });
    }

    public function setPriority(User $actor, User $subject, bool $priority): User
    {
        return DB::transaction(function () use ($actor, $subject, $priority): User {
            $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['priority' => $priority ? 1 : 0])->save();
            app(SecurityAudit::class)->record($actor, $priority ? 'marked contractor as priority.' : 'removed contractor priority.', $locked);

            return $locked->fresh();
        });
    }

    public function archive(User $actor, User $subject): User
    {
        return DB::transaction(function () use ($actor, $subject): User {
            $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            abort_if($locked->role() !== 'contractor', 422, 'Only contractors can be archived.');
            $state = $locked->accountState()->lockForUpdate()->firstOrCreate([], ['session_version' => 0]);
            if ($state->archived_at === null) {
                $state->archived_at = now();
                $state->session_version = ((int) $state->session_version) + 1;
                $state->save();
            }
            app(SecurityAudit::class)->record($actor, 'archived contractor account.', $locked);

            return $locked->fresh(['accountState']);
        });
    }

    public function restore(User $actor, User $subject): User
    {
        return DB::transaction(function () use ($actor, $subject): User {
            $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            $state = $locked->accountState()->lockForUpdate()->firstOrCreate([], ['session_version' => 0]);
            $state->forceFill(['archived_at' => null, 'session_version' => ((int) $state->session_version) + 1])->save();
            app(SecurityAudit::class)->record($actor, 'restored contractor account.', $locked);

            return $locked->fresh(['accountState']);
        });
    }

    /**
     * Permanently remove an unreferenced contractor while preserving activity-log history.
     * Archive is the normal deletion path; this route is intentionally guarded by reference checks.
     */
    public function delete(User $actor, User $subject): void
    {
        $paths = [];
        DB::transaction(function () use ($actor, $subject, &$paths): void {
            $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            abort_if($locked->role() !== 'contractor', 422, 'Only contractor accounts can be permanently deleted.');

            if ($locked->jobs()->exists() || $locked->jobNotes()->exists() || $locked->uploadedMedia()->exists()) {
                throw ValidationException::withMessages(['contractor' => 'This contractor has job history and can only be archived.']);
            }
            if (DB::table('Notes')->where('admin_id', $locked->user_id)->exists()) {
                throw ValidationException::withMessages(['contractor' => 'This contractor is recorded as a note author and can only be archived.']);
            }

            $fileIds = DB::table('Files')->where('user_id', $locked->user_id)->pluck('file_id')->all();
            if ($fileIds !== []) {
                $fileCopies = DB::table('app_private_files')->where('source_table', 'Files')->where('source_column', 'file_data')->whereIn('source_id', $fileIds)->get();
                foreach ($fileCopies as $copy) {
                    $paths[] = [$copy->disk, $copy->path];
                }
                DB::table('app_private_files')->where('source_table', 'Files')->where('source_column', 'file_data')->whereIn('source_id', $fileIds)->delete();
                DB::table('Files')->whereIn('file_id', $fileIds)->delete();
            }

            $copies = DB::table('app_private_files')->where('source_table', 'Users')->where('source_id', $locked->user_id)->get();
            foreach ($copies as $copy) {
                $paths[] = [$copy->disk, $copy->path];
            }
            DB::table('app_private_files')->where('source_table', 'Users')->where('source_id', $locked->user_id)->delete();

            $uploads = DB::table('app_private_uploads')->where('owner_user_id', $locked->user_id)->get();
            foreach ($uploads as $upload) {
                $paths[] = [$upload->disk, $upload->path];
            }
            DB::table('app_private_uploads')->where('owner_user_id', $locked->user_id)->delete();

            DB::table('Notes')->where('user_id', $locked->user_id)->delete();
            DB::table('ContractorServices')->where('user_id', $locked->user_id)->delete();
            DB::table('app_account_states')->where('user_id', $locked->user_id)->delete();
            DB::table('app_password_reset_tokens')->where('email', $locked->email)->delete();

            $subjectEventIds = DB::table('app_notification_events')->where('subject_user_id', $locked->user_id)->pluck('id')->all();
            $deliveryQuery = DB::table('app_notification_deliveries')->where('recipient_user_id', $locked->user_id);
            if ($subjectEventIds !== []) {
                $deliveryQuery->orWhereIn('event_id', $subjectEventIds);
            }
            $deliveryQuery->delete();
            if ($subjectEventIds !== []) {
                DB::table('app_notification_events')->whereIn('id', $subjectEventIds)->delete();
            }

            app(SecurityAudit::class)->record($actor, 'permanently deleted contractor account #'.$locked->user_id.'.');
            $locked->delete();
        });

        foreach ($paths as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                Log::warning('Private contractor deletion cleanup failed.', ['disk' => $disk, 'path' => $path, 'exception' => $exception]);
            }
        }
    }

    public function removeProfilePhoto(User $actor, User $subject): User
    {
        $removedPaths = [];

        $result = DB::transaction(function () use ($actor, $subject, &$removedPaths): User {
            $locked = User::query()->whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            abort_if($locked->role() !== 'contractor', 422, 'Only contractor photos can be removed.');
            $state = $locked->accountState()->lockForUpdate()->firstOrCreate([], ['session_version' => 0]);
            $upload = $locked->profileUpload()->lockForUpdate()->first();
            $changed = $upload !== null || $locked->profile_photo !== null;
            if (! $changed) {
                return $locked->fresh(['accountState']);
            }
            if ($upload) {
                $removedPaths[] = [$upload->disk, $upload->path];
                $upload->delete();
            }
            $locked->forceFill(['profile_photo' => null, 'is_approved' => 2, 'approved_date' => null])->save();
            $state->session_version = ((int) $state->session_version) + 1;
            $state->save();
            app(SecurityAudit::class)->record($actor, 'removed contractor profile photo.', $locked);
            app(BusinessNotifications::class)->contractorProfileChanged($actor, $locked);

            return $locked->fresh(['accountState']);
        });
        foreach ($removedPaths as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $result;
    }

    public function addNote(User $actor, User $subject, string $text): ContractorNote
    {
        return DB::transaction(function () use ($actor, $subject, $text): ContractorNote {
            $note = new ContractorNote;
            $note->forceFill([
                'user_id' => $subject->user_id,
                'admin_id' => $actor->user_id,
                'note_text' => trim($text),
                'created_at' => now(),
            ])->save();
            app(SecurityAudit::class)->record($actor, 'added a contractor note.', $subject);

            return $note;
        });
    }

    public function saveDocument(
        User $actor,
        User $owner,
        UploadedFile $file,
        string $fileType,
        ?string $displayName = null,
        ?CarbonInterface $expiry = null,
    ): ContractorDocument {
        $createdPaths = [];

        try {
            return DB::transaction(function () use ($actor, $owner, $file, $fileType, $displayName, $expiry, &$createdPaths): ContractorDocument {
                $lockedOwner = User::query()->whereKey($owner->user_id)->lockForUpdate()->firstOrFail();
                $bytes = $file->get();
                $upload = $this->storePrivateUpload($lockedOwner, $file, 'document', $fileType, $expiry, $createdPaths);
                $document = new ContractorDocument;
                $document->forceFill([
                    'user_id' => $lockedOwner->user_id,
                    'unique_file_id' => bin2hex(random_bytes(16)),
                    'file_type' => $fileType,
                    'file_name' => $this->safeName($displayName ?: $file->getClientOriginalName()),
                    'file_data' => $bytes,
                    'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                    'upload_date' => now(),
                    'expiry_date' => $expiry?->toDateString(),
                    'archived' => 0,
                ])->save();
                $upload->forceFill(['source_id' => $document->file_id])->save();
                app(SecurityAudit::class)->record($actor, 'uploaded a '.$fileType.' document.', $lockedOwner);

                return $document->fresh(['owner', 'privateUpload']);
            });
        } catch (Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }
    }

    public function archiveDocument(User $actor, ContractorDocument $document): ContractorDocument
    {
        return DB::transaction(function () use ($actor, $document): ContractorDocument {
            $locked = ContractorDocument::query()->whereKey($document->file_id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['archived' => 1])->save();
            app(SecurityAudit::class)->record($actor, 'archived a contractor document.', $locked->owner);

            return $locked->fresh(['owner', 'privateUpload']);
        });
    }

    /**
     * Permanently remove a newly uploaded document. Imported documents are retained and can be archived instead.
     */
    public function deleteDocument(User $actor, ContractorDocument $document): void
    {
        DB::transaction(function () use ($actor, $document): void {
            $locked = ContractorDocument::query()->with(['privateUpload', 'privateFile'])->whereKey($document->file_id)->lockForUpdate()->firstOrFail();
            abort_if($locked->privateFile !== null, 422, 'Imported documents can be archived but not permanently deleted.');
            $owner = $locked->owner;
            $upload = $locked->privateUpload;
            $locked->delete();
            if ($upload) {
                Storage::disk($upload->disk)->delete($upload->path);
                $upload->delete();
            }
            app(SecurityAudit::class)->record($actor, 'permanently deleted a contractor document.', $owner);
        });
    }

    /** @param list<string> $paths */
    private function storePrivateUpload(User $owner, UploadedFile $file, string $kind, string $fileType, ?CarbonInterface $expiry, array &$paths): PrivateUpload
    {
        $bytes = $file->get();
        $sha = hash('sha256', $bytes);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = 'm4/contractors/'.((int) $owner->user_id).'/'.$kind.'/'.Str::ulid().'-'.$sha.'.'.$extension;
        if (! Storage::disk('private')->put($path, $bytes)) {
            throw new RuntimeException('The private upload could not be stored.');
        }
        $paths[] = $path;
        $upload = new PrivateUpload;
        $upload->forceFill([
            'owner_user_id' => $owner->user_id,
            'source_table' => $kind === 'profile_photo' ? 'Users' : 'Files',
            'source_id' => $kind === 'profile_photo' ? $owner->user_id : 0,
            'source_column' => $kind === 'profile_photo' ? 'profile_photo' : 'file_data',
            'kind' => $kind,
            'disk' => 'private',
            'path' => $path,
            'byte_size' => strlen($bytes),
            'sha256' => $sha,
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'original_name' => $this->safeName($file->getClientOriginalName()),
            'file_type' => $kind === 'document' ? $fileType : null,
            'expiry_date' => $expiry?->toDateString(),
        ]);

        return $upload;
    }

    private function syncServices(User $contractor, array $serviceIds): void
    {
        $contractor->services()->sync(array_values(array_unique(array_map('intval', $serviceIds))));
    }

    private function safeName(?string $name): ?string
    {
        $name = trim(str_replace('\\', '/', (string) $name));
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9 ._()\-]/u', '_', $name) ?: '';

        return $name !== '' ? Str::limit($name, 255, '') : null;
    }
}
