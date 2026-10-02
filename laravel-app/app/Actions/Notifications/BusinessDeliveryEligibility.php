<?php

namespace App\Actions\Notifications;

use App\Models\ContractorDocument;
use App\Models\Job;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BusinessDeliveryEligibility
{
    public function allows(NotificationEvent $event, ?User $recipient, bool $mail = true): bool
    {
        if (! $recipient || ! $recipient->isActiveAccount() || ! $recipient->hasVerifiedEmail()
            || ($mail && (! config('notifications.business_enabled') || ! app(NotificationPreferences::class)->emailEnabled($recipient, $event->type)))
            || ! config('notifications.events.'.$event->type, false)) {
            return false;
        }
        $payload = $event->payload;
        if (isset($payload['job_id'])) {
            $job = Job::find($payload['job_id']);

            return $job !== null && Gate::forUser($recipient)->allows('view', $job);
        }
        if ($event->type === 'document.expiring') {
            $document = ContractorDocument::find($payload['file_id'] ?? 0);

            return $recipient->role() === 'admin' && $document !== null && (int) $document->archived === 0
                && strtolower($document->file_type) === 'insurance'
                && $document->expiry_date?->toDateString() === ($payload['expiry_date'] ?? null)
                && $document->expiry_date->betweenIncluded(($payload['stage'] ?? null) === -1 ? today()->subDays(30) : today(), today()->addDays(30))
                && $document->owner?->role() === 'contractor' && $document->owner->isActiveAccount();
        }
        if ($event->type === 'contractor.profile_changed') {
            $contractor = User::find($payload['contractor_id'] ?? 0);

            return $recipient->role() === 'admin' && $contractor?->role() === 'contractor' && $contractor->isActiveAccount();
        }

        return $event->type === 'contractor.approval_changed' && $recipient->role() === 'contractor' && (int) $event->subject_user_id === (int) $recipient->user_id;
    }

    public function url(NotificationEvent $event, User $recipient): string
    {
        $payload = $event->payload;
        if (isset($payload['job_id'])) {
            return route($recipient->role() === 'admin' ? 'admin.jobs.show' : 'contractor.jobs.show', $payload['job_id']);
        }

        return $payload['url'] ?? route('account.status');
    }
}
