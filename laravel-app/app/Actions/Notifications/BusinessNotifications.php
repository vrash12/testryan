<?php

namespace App\Actions\Notifications;

use App\Jobs\DeliverIdentityEvent;
use App\Models\Job;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BusinessNotifications
{
    public function contractorProfileChanged(User $actor, User $contractor): void
    {
        $this->emit('contractor.profile_changed', $contractor, $this->admins(), [
            'url' => route('admin.contractors.show', $contractor),
            'contractor_id' => $contractor->user_id,
            'actor_user_id' => $actor->user_id,
        ], 'contractor:'.$contractor->user_id.':profile:'.Str::ulid());
    }

    public function contractorApprovalChanged(User $actor, User $contractor): void
    {
        if (! $this->eligible($contractor)) {
            return;
        }

        $this->emit('contractor.approval_changed', $contractor, [$contractor], [
            'url' => route('account.status'),
            'approval' => $contractor->is_approved,
            'actor_user_id' => $actor->user_id,
        ], 'contractor:'.$contractor->user_id.':approval:'.Str::ulid());
    }

    public function jobAssigned(User $actor, Job $job): void
    {
        $contractor = $job->contractor;
        if (! $contractor || ! $this->eligible($contractor)) {
            return;
        }

        $this->emit('job.assigned', $contractor, [$contractor], [
            'url' => route('contractor.jobs.show', $job),
            'job_id' => $job->job_id,
        ], 'job:'.$job->job_id.':assigned:'.Str::ulid());
    }

    public function jobUpdated(User $actor, Job $job): void
    {
        $contractor = $job->contractor;
        if (! $contractor || ! $this->eligible($contractor)) {
            return;
        }

        $this->emit('job.updated', $contractor, [$contractor], [
            'url' => route('contractor.jobs.show', $job),
            'job_id' => $job->job_id,
        ], 'job:'.$job->job_id.':updated:'.Str::ulid());
    }

    public function jobAccepted(User $actor, Job $job): void
    {
        $this->emit('job.accepted', $job->contractor ?: $actor, $this->admins(), [
            'url' => route('admin.jobs.show', $job),
            'job_id' => $job->job_id,
            'actor_user_id' => $actor->user_id,
        ], 'job:'.$job->job_id.':accepted:'.Str::ulid());
    }

    public function jobCompleted(User $actor, Job $job): void
    {
        $recipients = $this->admins();
        if ($job->contractor) {
            $recipients[] = $job->contractor;
        }
        $this->emit('job.completed', $job->contractor ?: $actor, $recipients, [
            'url' => route('admin.jobs.show', $job),
            'job_id' => $job->job_id,
            'actor_user_id' => $actor->user_id,
        ], 'job:'.$job->job_id.':completed:'.Str::ulid());
    }

    public function jobNoteAdded(User $actor, Job $job): void
    {
        $recipients = $actor->role() === 'admin'
            ? ($job->contractor && $this->eligible($job->contractor) ? [$job->contractor] : [])
            : $this->admins();
        $subject = $job->contractor ?: $actor;
        $url = $actor->role() === 'admin' ? route('contractor.jobs.show', $job) : route('admin.jobs.show', $job);
        $this->emit('job.note_added', $subject, $recipients, ['url' => $url, 'job_id' => $job->job_id, 'actor_user_id' => $actor->user_id], 'job:'.$job->job_id.':note:'.Str::ulid());
    }

    public function jobMediaUploaded(User $actor, Job $job, int $count = 1, ?string $uploadGroup = null): void
    {
        $recipients = $actor->role() === 'admin'
            ? ($job->contractor && $this->eligible($job->contractor) ? [$job->contractor] : [])
            : $this->admins();
        $subject = $job->contractor ?: $actor;
        $url = $actor->role() === 'admin' ? route('contractor.jobs.show', $job) : route('admin.jobs.show', $job);
        // Browser files commit separately. Notify on the first successful file in
        // this selection; the generic message does not promise a batch total.
        $identity = $uploadGroup === null ? (string) Str::ulid() : $actor->user_id.':'.$uploadGroup;
        $this->emit('job.media_uploaded', $subject, $recipients, ['url' => $url, 'job_id' => $job->job_id, 'count' => $uploadGroup === null ? $count : null, 'actor_user_id' => $actor->user_id], 'job:'.$job->job_id.':media:'.$identity);
    }

    public function documentExpiring(User $owner, int $fileId, string $expiryDate, ?int $stage = null): void
    {
        $this->emit('document.expiring', $owner, $this->admins(), [
            'url' => route('admin.contractors.documents.index', $owner),
            'file_id' => $fileId,
            'expiry_date' => $expiryDate,
            'stage' => $stage,
        ], 'document:'.$fileId.':'.$expiryDate.($stage === null || $stage === 30 ? '' : ':stage:'.$stage));
    }

    /** @param list<User> $recipients */
    private function emit(string $type, User $subject, array $recipients, array $payload, string $identity): void
    {
        if ((! config('notifications.business_enabled') && ! config('notifications.inbox_enabled')) || ! config('notifications.events.'.$type, true) || $recipients === []) {
            return;
        }

        $recipients = collect($recipients)->filter(fn (User $user): bool => $this->eligible($user) && $user->user_id !== ($payload['actor_user_id'] ?? null))->unique('user_id')->values()->all();
        if ($recipients === []) {
            return;
        }

        DB::transaction(function () use ($type, $subject, $recipients, $payload, $identity): void {
            $key = hash('sha256', $type.':'.$identity);
            $event = NotificationEvent::unguarded(fn () => NotificationEvent::firstOrCreate(['deduplication_key' => $key], [
                'type' => $type,
                'subject_user_id' => $subject->user_id,
                'payload' => $payload,
                'created_at' => now(),
            ]));
            if (! $event->wasRecentlyCreated) {
                return;
            }
            foreach ($recipients as $recipient) {
                app(NotificationInbox::class)->record($event, $recipient);
                if (! config('notifications.business_enabled') || ! app(NotificationPreferences::class)->emailEnabled($recipient, $type)) {
                    continue;
                }
                (new NotificationDelivery)->forceFill([
                    'event_id' => $event->id,
                    'recipient_user_id' => $recipient->user_id,
                    'recipient_email' => $recipient->email,
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => now(),
                ])->save();
            }
            if (! $event->deliveries()->exists()) {
                return;
            }
            DB::afterCommit(function () use ($event): void {
                try {
                    DeliverIdentityEvent::dispatch($event->id);
                } catch (Throwable $exception) {
                    Log::warning('Business event remains pending after dispatch failure.', ['event_id' => $event->id, 'exception_class' => $exception::class]);
                }
            });
        });
    }

    /** @return list<User> */
    private function admins(): array
    {
        return User::query()->where('is_admin', 1)->get()->filter(fn (User $user): bool => $this->eligible($user))->values()->all();
    }

    private function eligible(?User $user): bool
    {
        return $user !== null && $user->isActiveAccount() && $user->hasVerifiedEmail() && filled($user->email);
    }
}
