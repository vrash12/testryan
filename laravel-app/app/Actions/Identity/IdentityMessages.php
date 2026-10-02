<?php

namespace App\Actions\Identity;

use App\Actions\Notifications\NotificationInbox;
use App\Actions\Notifications\NotificationPreferences;
use App\Jobs\DeliverIdentityEvent;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class IdentityMessages
{
    public function verification(User $user): void
    {
        if (! $user->isActiveAccount() || ! $user->email || $user->hasVerifiedEmail()) {
            return;
        }
        $expires = now()->addMinutes(config('identity.verification_minutes'));
        $url = URL::temporarySignedRoute('verification.verify', $expires, ['id' => $user->user_id, 'hash' => sha1($user->getEmailForVerification())]);
        $this->record('email.verification', $user, [$user], ['url' => $url, 'expires_at' => $expires->timestamp], $user->user_id.':'.$user->email.':'.intdiv(now()->timestamp, 60));
    }

    public function recovery(User $user, #[\SensitiveParameter] string $token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
        $this->record('password.recovery', $user, [$user], ['url' => $url, 'expires_at' => now()->addMinutes(60)->timestamp], hash('sha256', $token));
    }

    public function registration(User $user): void
    {
        $recipients = (config('identity.registration_admin_mail') || config('notifications.inbox_enabled'))
            ? User::where('is_admin', 1)->get()->filter(fn (User $admin) => $admin->isActiveAccount() && $admin->hasVerifiedEmail())->all() : [];
        $this->record('registration.created', $user, $recipients, ['url' => route('admin.accounts.index'), 'expires_at' => null, 'eligible_recipients' => count($recipients)], (string) $user->user_id);
    }

    private function record(string $type, User $subject, array $recipients, array $payload, string $identity): void
    {
        DB::transaction(function () use ($type, $subject, $recipients, $payload, $identity): void {
            $key = hash('sha256', $type.':'.$identity);
            if (NotificationEvent::where('deduplication_key', $key)->exists()) {
                return;
            }
            $event = (new NotificationEvent)->forceFill(['type' => $type, 'subject_user_id' => $subject->user_id, 'deduplication_key' => $key, 'payload' => $payload, 'created_at' => now()]);
            $event->save();
            foreach ($recipients as $recipient) {
                if ($type === 'registration.created') {
                    app(NotificationInbox::class)->record($event, $recipient);
                    if (! config('identity.registration_admin_mail') || ! app(NotificationPreferences::class)->emailEnabled($recipient, $type)) {
                        continue;
                    }
                }
                (new NotificationDelivery)->forceFill(['event_id' => $event->id, 'recipient_user_id' => $recipient->user_id, 'recipient_email' => $recipient->email, 'status' => 'pending', 'attempts' => 0, 'available_at' => now()])->save();
            }
            DB::afterCommit(function () use ($event): void {
                try {
                    DeliverIdentityEvent::dispatch($event->id);
                } catch (Throwable $exception) {
                    Log::warning('Identity event remains pending after dispatch failure.', ['event_id' => $event->id, 'exception_class' => $exception::class]);
                }
            });
        });
    }
}
