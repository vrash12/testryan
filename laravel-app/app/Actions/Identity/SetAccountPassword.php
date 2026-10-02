<?php

namespace App\Actions\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class SetAccountPassword
{
    public function run(User $subject, #[\SensitiveParameter] string $password, ?User $actor = null): void
    {
        $hash = Hash::make($password);
        DB::transaction(function () use ($subject, $hash, $actor): void {
            $locked = User::lockForUpdate()->findOrFail($subject->user_id);
            if ($actor) {
                $actor->refresh();
                Gate::forUser($actor)->authorize('resetPassword', $locked);
            }
            abort_unless($locked->isActiveAccount(), 403);
            $locked->password_hash = $hash;
            $locked->save();
            $state = $locked->accountState()->lockForUpdate()->firstOrNew();
            $state->session_version = ($state->session_version ?? 0) + 1;
            $state->save();
            if ($locked->email) {
                DB::table(config('auth.passwords.users.table'))->where('email', $locked->email)->delete();
            }
            app(SecurityAudit::class)->record($actor ?? $locked, $actor
                ? 'admin '.$actor->user_id.' reset password for account '.$locked->user_id.'; sessions revoked.'
                : 'password reset through recovery; sessions revoked.');
        });
    }
}
