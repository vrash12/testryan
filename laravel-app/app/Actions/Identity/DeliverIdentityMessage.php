<?php

namespace App\Actions\Identity;

use App\Actions\Notifications\BusinessDeliveryEligibility;
use App\Actions\Notifications\NotificationPreferences;
use App\Models\NotificationDelivery;
use App\Notifications\BusinessNotification;
use App\Notifications\IdentityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Throwable;

class DeliverIdentityMessage
{
    public function run(string $id): void
    {
        // A row lock serializes delivery workers. A crash after SMTP handoff can still be ambiguous.
        DB::transaction(function () use ($id): void {
            $delivery = NotificationDelivery::lockForUpdate()->find($id);
            if (! $delivery || ! in_array($delivery->status, ['pending', 'failed'], true) || $delivery->available_at->isFuture()) {
                return;
            }
            $event = $delivery->event;
            $recipient = $delivery->recipient;
            $payload = $event->payload;
            $eligible = $recipient && $recipient->isActiveAccount() && $recipient->email === $delivery->recipient_email;
            if ($event->type === 'registration.created') {
                $eligible = $eligible && $recipient->role() === 'admin' && $recipient->hasVerifiedEmail()
                    && config('identity.registration_admin_mail')
                    && app(NotificationPreferences::class)->emailEnabled($recipient, $event->type);
            }

            if (! in_array($event->type, ['email.verification', 'password.recovery', 'registration.created'], true)) {
                $eligible = $eligible && app(BusinessDeliveryEligibility::class)->allows($event, $recipient);
            }
            if ($event->type === 'email.verification' && $recipient?->hasVerifiedEmail()) {
                $eligible = false;
            }
            if ($eligible && $event->type === 'password.recovery') {
                $token = basename(parse_url($payload['url'], PHP_URL_PATH));
                $eligible = Password::tokenExists($recipient, $token);
            }
            if (($payload['expires_at'] ?? null) !== null && $payload['expires_at'] < now()->timestamp) {
                $eligible = false;
            }
            if (! $eligible) {
                $delivery->forceFill(['status' => 'skipped', 'last_error' => 'Recipient or token is no longer eligible.'])->save();

                return;
            }
            try {
                if (app()->environment(['local', 'testing']) && ! in_array(config('mail.default'), ['log', 'array'], true)) {
                    throw new RuntimeException('External notification mail is disabled in local/testing.');
                }
                $notification = in_array($event->type, ['email.verification', 'password.recovery', 'registration.created'], true)
                    ? new IdentityNotification($event->type, $payload['url'])
                    : new BusinessNotification($event->type, array_replace($payload, ['url' => app(BusinessDeliveryEligibility::class)->url($event, $recipient)]));
                Notification::sendNow($recipient, $notification);
                $delivery->forceFill(['status' => 'delivered', 'attempts' => $delivery->attempts + 1, 'delivered_at' => now(), 'last_error' => null])->save();
            } catch (Throwable $exception) {
                $attempts = $delivery->attempts + 1;
                $delivery->forceFill(['status' => 'failed', 'attempts' => $attempts, 'available_at' => now()->addSeconds(min(3600, 60 * (2 ** min($attempts, 6)))), 'last_error' => $exception::class])->save();
                Log::warning('Notification mail delivery failed; retry is retained privately.', ['delivery_id' => $id, 'exception_class' => $exception::class]);
            }
        });
    }
}
