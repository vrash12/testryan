<?php

namespace Tests\Feature;

use App\Actions\Identity\DeliverIdentityMessage;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Notifications\IdentityNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class IdentityDeliveryTest extends IdentityTestCase
{
    public function test_transport_failure_is_private_durable_retryable_and_does_not_duplicate_delivery(): void
    {
        $user = $this->unverify(2);
        Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('fixture-smtp-secret'));
        $user->sendEmailVerificationNotification();
        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertStringNotContainsString('fixture-smtp-secret', $delivery->last_error);
        Notification::fake();
        $this->travel(10)->minutes();
        $this->artisan('nestcrafters:retry-identity-mail')->assertSuccessful();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->artisan('nestcrafters:retry-identity-mail')->assertSuccessful();
        Notification::assertSentToTimes($user, IdentityNotification::class, 1);
        $this->assertSame(1, NotificationEvent::count());
    }

    public function test_dispatch_failure_leaves_an_intent_that_can_be_delivered_later(): void
    {
        $user = $this->unverify(2);
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('Synthetic dispatch failure'));
        $user->sendEmailVerificationNotification();
        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('delivered', $delivery->fresh()->status);
        Notification::assertSentTo($user, IdentityNotification::class);
    }

    public function test_recipient_changes_expiry_and_external_local_transport_fail_closed(): void
    {
        Queue::fake();
        $user = $this->unverify(2);
        $user->sendEmailVerificationNotification();
        $delivery = NotificationDelivery::firstOrFail();
        User::where('user_id', 2)->update(['email' => 'changed@example.test']);
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Notification::assertNothingSent();
        $new = User::findOrFail(2);
        $new->sendEmailVerificationNotification();
        $second = NotificationDelivery::where('status', 'pending')->firstOrFail();
        config(['mail.default' => 'smtp']);
        app(DeliverIdentityMessage::class)->run($second->id);
        $this->assertSame('failed', $second->fresh()->status);
        Notification::assertNothingSent();
        $this->travel(61)->minutes();
        app(DeliverIdentityMessage::class)->run($second->id);
        $this->assertSame('skipped', $second->fresh()->status);
    }

    public function test_queued_recovery_is_skipped_after_the_token_has_been_revoked(): void
    {
        Queue::fake();
        $this->post('/forgot-password', ['email' => 'contractor@example.test'])->assertSessionHas('status');
        $delivery = NotificationDelivery::firstOrFail();
        Password::deleteToken(User::findOrFail(2));
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Notification::assertNothingSent();
    }
}
