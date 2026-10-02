<?php

namespace App\Actions\Job;

use App\Actions\Notifications\BusinessNotifications;
use App\Enums\MediaTag;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobMedia;
use App\Models\JobNote;
use App\Models\JobTransition;
use App\Models\PrivateUpload;
use App\Models\Service;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class JobManagement
{
    private const MEDIA_MIME = ['image/jpeg', 'image/png', 'application/pdf'];

    public function create(User $actor, array $data): Job
    {
        $this->validateMoney($data['approved_price'] ?? null, $data['deposit_amount'] ?? null);

        return DB::transaction(function () use ($actor, $data): Job {
            $contractor = $this->contractorForAssignment($data['contractor_id'] ?? null);
            $job = new Job;
            $job->forceFill([
                'entered_datetime' => now(),
                'due_date' => $data['due_date'] ?? null,
                'job_name' => trim($data['job_name']),
                'job_description' => $data['job_description'],
                'is_accepted' => 0,
                'accepted_datetime' => null,
                'terms' => $data['terms'] ?? null,
                'contractor_id' => $contractor?->user_id,
                'is_completed' => 0,
                'completed_datetime' => null,
                'approved_price' => $this->money($data['approved_price'] ?? null),
                'deposit_amount' => $this->money($data['deposit_amount'] ?? null),
                'who_supplies_materials' => $data['who_supplies_materials'] ?? null,
            ])->save();
            $job->services()->sync($this->serviceIds($data['services']));
            $this->transition($job, $actor, 'created', $job->status()->value, $job->status()->value, null, $job->contractor_id);
            $this->audit($actor, 'created job #'.$job->job_id.'.');
            if ($contractor) {
                app(BusinessNotifications::class)->jobAssigned($actor, $job);
            }

            return $job->fresh(['contractor', 'services']);
        });
    }

    public function update(User $actor, Job $job, array $data): Job
    {
        $this->validateMoney($data['approved_price'] ?? null, $data['deposit_amount'] ?? null);

        return DB::transaction(function () use ($actor, $job, $data): Job {
            $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
            $beforeServices = $locked->services()->pluck('Services.service_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $locked->forceFill([
                'due_date' => $data['due_date'] ?? null,
                'job_name' => trim($data['job_name']),
                'job_description' => $data['job_description'],
                'terms' => $data['terms'] ?? null,
                'approved_price' => $this->money($data['approved_price'] ?? null),
                'deposit_amount' => $this->money($data['deposit_amount'] ?? null),
                'who_supplies_materials' => $data['who_supplies_materials'] ?? null,
            ])->save();
            $detailsChanged = $locked->wasChanged(['due_date', 'job_name', 'job_description', 'terms', 'approved_price', 'deposit_amount', 'who_supplies_materials']);
            $locked->services()->sync($this->serviceIds($data['services']));
            $afterServices = $locked->services()->pluck('Services.service_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($detailsChanged || $beforeServices !== $afterServices) {
                $this->audit($actor, 'updated job #'.$locked->job_id.'.');
                app(BusinessNotifications::class)->jobUpdated($actor, $locked);
            }

            return $locked->fresh(['contractor', 'services']);
        });
    }

    public function assign(User $actor, Job $job, ?int $contractorId): Job
    {
        return DB::transaction(function () use ($actor, $job, $contractorId): Job {
            $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
            if ($locked->is_completed === 1) {
                throw ValidationException::withMessages(['contractor_id' => 'Reopen the completed job before changing its assignment.']);
            }
            $contractor = $this->contractorForAssignment($contractorId);
            $changed = (int) $locked->contractor_id !== (int) ($contractor?->user_id ?? 0);
            if ($changed) {
                $fromStatus = $locked->status()->value;
                $fromContractor = $locked->contractor_id;
                $locked->forceFill([
                    'contractor_id' => $contractor?->user_id,
                    'is_accepted' => 0,
                    'accepted_datetime' => null,
                    'is_completed' => 0,
                    'completed_datetime' => null,
                ])->save();
                $this->transition($locked, $actor, 'assignment', $fromStatus, $locked->status()->value, $fromContractor, $locked->contractor_id);
                $this->audit($actor, 'assigned job #'.$locked->job_id.($contractor ? ' to contractor #'.$contractor->user_id.'.' : ' as unassigned.'));
                app(BusinessNotifications::class)->jobAssigned($actor, $locked);
            }

            return $locked->fresh(['contractor', 'services']);
        });
    }

    public function accept(User $actor, Job $job): Job
    {
        return DB::transaction(function () use ($actor, $job): Job {
            $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
            if ($locked->contractor_id === null) {
                throw ValidationException::withMessages(['job' => 'An unassigned job cannot be accepted.']);
            }
            if ($actor->role() !== 'admin' && (int) $locked->contractor_id !== (int) $actor->user_id) {
                abort(403);
            }
            if ($locked->is_completed === 1) {
                throw ValidationException::withMessages(['job' => 'A completed job cannot be accepted again.']);
            }
            if ((int) $locked->is_accepted !== 1) {
                $fromStatus = $locked->status()->value;
                $locked->forceFill(['is_accepted' => 1, 'accepted_datetime' => now()])->save();
                $this->transition($locked, $actor, 'acceptance', $fromStatus, $locked->status()->value);
                $this->audit($actor, 'accepted job #'.$locked->job_id.'.');
                app(BusinessNotifications::class)->jobAccepted($actor, $locked);
            } else {
                return $locked->fresh(['contractor', 'services']);
            }

            return $locked->fresh(['contractor', 'services']);
        });
    }

    public function complete(User $actor, Job $job, bool $completed): Job
    {
        return DB::transaction(function () use ($actor, $job, $completed): Job {
            $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
            if ($completed && ((int) $locked->contractor_id === 0 || $locked->is_accepted !== 1)) {
                throw ValidationException::withMessages(['job' => 'A job must be assigned and accepted before completion.']);
            }
            if ((int) $locked->is_completed === ($completed ? 1 : 0)) {
                return $locked->fresh(['contractor', 'services']);
            }
            $fromStatus = $locked->status()->value;
            $locked->forceFill([
                'is_completed' => $completed ? 1 : 0,
                'completed_datetime' => $completed ? now() : null,
            ])->save();
            $this->transition($locked, $actor, $completed ? 'completion' : 'reopen', $fromStatus, $locked->status()->value);
            $this->audit($actor, ($completed ? 'completed' : 'reopened').' job #'.$locked->job_id.'.');
            if ($completed) {
                app(BusinessNotifications::class)->jobCompleted($actor, $locked);
            }

            return $locked->fresh(['contractor', 'services']);
        });
    }

    public function delete(User $actor, Job $job): void
    {
        $paths = [];
        DB::transaction(function () use ($actor, $job, &$paths): void {
            $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
            $mediaIds = $locked->media()->pluck('media_id')->all();
            if ($mediaIds !== [] && DB::table('app_private_files')->where('source_table', 'Job_Media')->where('source_column', 'file_data')->whereIn('source_id', $mediaIds)->exists()) {
                throw ValidationException::withMessages(['job' => 'Imported job media is retained as historical evidence; archive the job instead.']);
            }
            if ($mediaIds !== []) {
                $uploads = DB::table('app_private_uploads')->where('source_table', 'Job_Media')->where('source_column', 'file_data')->whereIn('source_id', $mediaIds)->get();
                foreach ($uploads as $upload) {
                    $paths[] = [$upload->disk, $upload->path];
                }
                DB::table('app_private_uploads')->where('source_table', 'Job_Media')->where('source_column', 'file_data')->whereIn('source_id', $mediaIds)->delete();
                DB::table('Job_Media')->whereIn('media_id', $mediaIds)->delete();
            }
            DB::table('Job_Notes')->where('job_id', $locked->job_id)->delete();
            DB::table('Job_Services')->where('job_id', $locked->job_id)->delete();
            $this->transition($locked, $actor, 'deleted', $locked->status()->value, 'deleted', $locked->contractor_id, null);
            $this->audit($actor, 'deleted job #'.$locked->job_id.'.');
            $locked->delete();
        });
        foreach ($paths as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function addNote(User $actor, Job $job, string $text): JobNote
    {
        return DB::transaction(function () use ($actor, $job, $text): JobNote {
            $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
            if ($actor->role() !== 'admin' && (int) $locked->contractor_id !== (int) $actor->user_id) {
                abort(403);
            }
            if ($actor->role() === 'contractor' && $locked->is_completed === 1) {
                abort(403);
            }
            $note = new JobNote;
            $note->forceFill([
                'job_id' => $locked->job_id,
                'note_text' => trim($text),
                'created_datetime' => now(),
                'created_by' => $actor->user_id,
            ])->save();
            $this->audit($actor, 'added a note to job #'.$locked->job_id.'.');
            app(BusinessNotifications::class)->jobNoteAdded($actor, $locked);

            return $note->fresh(['job', 'author']);
        });
    }

    /** @param list<UploadedFile> $files */
    public function addMedia(User $actor, Job $job, array $files, MediaTag $tag, ?string $uploadId = null, ?string $uploadGroup = null): array
    {
        $createdPaths = [];
        try {
            return DB::transaction(function () use ($actor, $job, $files, $tag, $uploadId, $uploadGroup, &$createdPaths): array {
                $receiptId = null;
                $receipt = null;
                if ($uploadId !== null) {
                    $receiptId = hash('sha256', $actor->user_id.':'.$job->job_id.':'.$uploadId);
                    $fingerprint = hash('sha256', json_encode([$tag->value, $uploadGroup, array_map(fn (UploadedFile $file) => [$file->getClientOriginalName(), hash_file('sha256', $file->getRealPath())], $files)], JSON_THROW_ON_ERROR));
                    DB::table('app_media_upload_receipts')->insertOrIgnore([
                        'id' => $receiptId, 'user_id' => $actor->user_id, 'fingerprint' => $fingerprint, 'created_at' => now(),
                    ]);
                    $receipt = DB::table('app_media_upload_receipts')->where('id', $receiptId)->lockForUpdate()->first();
                    abort_unless($receipt && hash_equals($receipt->fingerprint, $fingerprint), 409, 'This upload reference was already used for different files or a different tag.');
                }
                $locked = Job::query()->whereKey($job->job_id)->lockForUpdate()->firstOrFail();
                if ($actor->role() !== 'admin' && (int) $locked->contractor_id !== (int) $actor->user_id) {
                    abort(403);
                }
                if ($actor->role() === 'contractor' && $locked->is_accepted !== 1) {
                    throw ValidationException::withMessages(['job' => 'Accept the job before uploading media.']);
                }
                if ($actor->role() === 'contractor' && $locked->is_completed === 1) {
                    abort(403);
                }
                if ($receipt?->media_ids !== null) {
                    $ids = json_decode($receipt->media_ids, true, flags: JSON_THROW_ON_ERROR);
                    $stored = $locked->media()->whereIn('media_id', $ids)->where('uploaded_by', $actor->user_id)->get();
                    abort_unless($stored->count() === count($ids), 409, 'Previously uploaded media was removed. Refresh the job before selecting a new upload.');

                    return $stored->all();
                }
                $media = [];
                foreach ($files as $file) {
                    $mime = (string) ($file->getMimeType() ?: '');
                    if (! in_array($mime, self::MEDIA_MIME, true)) {
                        throw ValidationException::withMessages(['media' => 'Only JPEG, PNG, and PDF media are allowed.']);
                    }
                    $bytes = $file->get();
                    $sha = hash('sha256', $bytes);
                    $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
                    $path = 'm5/jobs/'.$locked->job_id.'/'.Str::ulid().'-'.$sha.'.'.$extension;
                    if (! Storage::disk('private')->put($path, $bytes)) {
                        throw new RuntimeException('The private job media could not be stored.');
                    }
                    $createdPaths[] = $path;
                    $row = new JobMedia;
                    $row->forceFill([
                        'job_id' => $locked->job_id,
                        'file_name' => $this->safeName($file->getClientOriginalName()),
                        'file_data' => $bytes,
                        'mime_type' => $mime,
                        'tag' => $tag->value,
                        'upload_datetime' => now(),
                        'uploaded_by' => $actor->user_id,
                    ])->save();
                    $upload = new PrivateUpload;
                    $upload->forceFill([
                        'owner_user_id' => $actor->user_id,
                        'source_table' => 'Job_Media',
                        'source_id' => $row->media_id,
                        'source_column' => 'file_data',
                        'kind' => 'job_media',
                        'disk' => 'private',
                        'path' => $path,
                        'byte_size' => strlen($bytes),
                        'sha256' => $sha,
                        'mime_type' => $mime,
                        'original_name' => $this->safeName($file->getClientOriginalName()),
                    ])->save();
                    $media[] = $row->fresh(['privateUpload']);
                }
                if ($receiptId !== null) {
                    DB::table('app_media_upload_receipts')->where('id', $receiptId)->update(['media_ids' => json_encode(array_map(fn (JobMedia $row) => $row->media_id, $media), JSON_THROW_ON_ERROR)]);
                }
                $this->audit($actor, 'uploaded '.count($media).' media file(s) to job #'.$locked->job_id.'.');
                app(BusinessNotifications::class)->jobMediaUploaded($actor, $locked, count($media), $uploadGroup);

                return $media;
            });
        } catch (Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('private')->delete($path);
            }
            throw $exception;
        }
    }

    public function deleteMedia(User $actor, JobMedia $media): void
    {
        $removed = DB::transaction(function () use ($actor, $media): ?array {
            $locked = JobMedia::query()->with(['privateUpload', 'privateFile'])->whereKey($media->media_id)->lockForUpdate()->firstOrFail();
            if ($locked->privateFile !== null) {
                throw ValidationException::withMessages(['media' => 'Imported media is retained as historical evidence.']);
            }
            $job = $locked->job;
            $upload = $locked->privateUpload;
            $locked->delete();
            if ($upload) {
                $upload->delete();
            }
            $this->audit($actor, 'deleted media from job #'.$job->job_id.'.');

            return $upload ? [$upload->disk, $upload->path] : null;
        });
        if ($removed) {
            Storage::disk($removed[0])->delete($removed[1]);
        }
    }

    /** @return list<int> */
    private function serviceIds(array $services): array
    {
        $ids = array_values(array_unique(array_map('intval', $services)));
        if ($ids === [] || count($ids) !== Service::query()->whereIn('service_id', $ids)->count()) {
            throw ValidationException::withMessages(['services' => 'Select at least one valid service.']);
        }

        return $ids;
    }

    private function contractorForAssignment(?int $contractorId): ?User
    {
        if ($contractorId === null || $contractorId === 0) {
            return null;
        }
        $contractor = User::query()->whereKey($contractorId)->lockForUpdate()->first();
        if (! $contractor || $contractor->role() !== 'contractor' || ! $contractor->isActiveAccount() || $contractor->is_approved !== 1) {
            throw ValidationException::withMessages(['contractor_id' => 'Choose an active approved contractor.']);
        }

        return $contractor;
    }

    private function validateMoney(mixed $price, mixed $deposit): void
    {
        if ($deposit !== null && $deposit !== '' && BigDecimal::of((string) $deposit)->isGreaterThan(BigDecimal::of((string) ($price ?? 0)))) {
            throw ValidationException::withMessages(['deposit_amount' => 'The deposit cannot exceed the approved price.']);
        }
    }

    private function money(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : BigDecimal::of((string) $value)->toScale(2)->__toString();
    }

    private function safeName(?string $name): string
    {
        $name = basename(str_replace('\\', '/', trim((string) $name)));

        return Str::limit(preg_replace('/[^A-Za-z0-9 ._()\-]/u', '_', $name) ?: 'media', 255, '');
    }

    private function audit(User $actor, string $action): void
    {
        (new ActivityLog)->forceFill([
            'user_id' => $actor->user_id,
            'action' => 'Job: '.$action,
            'timestamp' => now(),
        ])->save();
    }

    private function transition(Job $job, User $actor, string $action, string $fromStatus, string $toStatus, ?int $fromContractor = null, ?int $toContractor = null): void
    {
        JobTransition::query()->create([
            'job_id' => $job->job_id,
            'actor_user_id' => $actor->user_id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_contractor_id' => $fromContractor,
            'to_contractor_id' => $toContractor,
            'from_state' => ['contractor_id' => $fromContractor, 'status' => $fromStatus],
            'to_state' => ['contractor_id' => $toContractor, 'status' => $toStatus, 'is_accepted' => $toStatus === 'accepted' ? 1 : 0, 'is_completed' => $toStatus === 'completed' ? 1 : 0],
        ]);
    }
}
