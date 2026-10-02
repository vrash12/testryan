<?php

namespace App\Actions\Notifications;

use App\Models\InboxNotification;
use App\Models\NotificationEvent;
use App\Models\User;

class NotificationInbox
{
    public function record(NotificationEvent $event, User $user): void
    {
        if (! config('notifications.inbox_enabled') || ! isset(NotificationPreferences::LABELS[$event->type])) {
            return;
        }
        InboxNotification::unguarded(fn () => InboxNotification::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->user_id], ['created_at' => now()]
        ));
    }

    public function url(NotificationEvent $event, User $user): ?string
    {
        if ($event->type === 'registration.created') {
            $subject = User::find($event->subject_user_id);

            return $user->role() === 'admin' && $subject?->role() === 'contractor' && $subject->isActiveAccount()
                ? route('admin.contractors.show', $subject) : null;
        }
        $eligibility = app(BusinessDeliveryEligibility::class);

        return $eligibility->allows($event, $user, false) ? $eligibility->url($event, $user) : null;
    }
}
