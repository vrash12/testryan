<?php

namespace App\Actions\Identity;

use App\Models\ActivityLog;
use App\Models\User;

class SecurityAudit
{
    public function record(User $actor, string $action, ?User $subject = null): void
    {
        $suffix = $subject && (int) $subject->user_id !== (int) $actor->user_id
            ? ' (account #'.$subject->user_id.')'
            : '';
        (new ActivityLog)->forceFill(['user_id' => $actor->user_id, 'action' => 'Security: '.$action.$suffix, 'timestamp' => now()])->save();
    }
}
