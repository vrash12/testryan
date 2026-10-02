<?php

namespace App\Actions\Contractor;

use App\Actions\Identity\SecurityAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BlockContractor
{
    public function run(User $actor, User $subject, bool $blocked, string $reason): void
    {
        DB::transaction(function () use ($actor, $subject, $blocked, $reason): void {
            $locked = User::whereKey($subject->user_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('block', $locked);
            $state = $locked->accountState()->lockForUpdate()->firstOrCreate([], ['session_version' => 0]);
            if (($state->blocked_at !== null) === $blocked) {
                return;
            }
            $state->forceFill(['blocked_at' => $blocked ? now() : null, 'session_version' => $state->session_version + 1])->save();
            // Unblocking restores status/verification access, never implicit work approval.
            if (! $blocked) {
                $locked->forceFill(['is_approved' => 2, 'approved_date' => null])->save();
            }
            app(SecurityAudit::class)->record($actor, ($blocked ? 'blocked contractor access.' : 'unblocked contractor; approval review required.').' Reason: '.trim($reason), $locked);
        });
    }
}
