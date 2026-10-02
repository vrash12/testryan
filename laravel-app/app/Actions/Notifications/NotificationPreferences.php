<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationPreferences
{
    public const LABELS = [
        'registration.created' => 'New contractor registrations',
        'contractor.profile_changed' => 'Contractor profile changes',
        'contractor.approval_changed' => 'Account approval changes',
        'job.assigned' => 'Job assignments',
        'job.updated' => 'Job detail changes',
        'job.accepted' => 'Job acceptance',
        'job.completed' => 'Job completion',
        'job.note_added' => 'New job notes',
        'job.media_uploaded' => 'New job media',
        'document.expiring' => 'Insurance reminders',
    ];

    public function choices(User $user): array
    {
        $types = $user->role() === 'admin'
            ? ['registration.created', 'contractor.profile_changed', 'job.accepted', 'job.completed', 'job.note_added', 'job.media_uploaded', 'document.expiring']
            : ['contractor.approval_changed', 'job.assigned', 'job.updated', 'job.completed', 'job.note_added', 'job.media_uploaded'];

        return array_intersect_key(self::LABELS, array_flip($types));
    }

    public function emailEnabled(User $user, string $type): bool
    {
        // Verification and password recovery are essential security messages.
        if (! isset(self::LABELS[$type])) {
            return true;
        }

        return (bool) (DB::table('app_notification_preferences')->where('user_id', $user->user_id)
            ->where('event_type', $type)->value('email_enabled') ?? true);
    }
}
