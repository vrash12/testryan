<?php

namespace Tests\Feature;

use App\Actions\Identity\DeliverIdentityMessage;
use App\Actions\Identity\IdentityMessages;
use App\Actions\Notifications\BusinessNotifications;
use App\Jobs\DeliverIdentityEvent;
use App\Jobs\DeliverNotificationDelivery;
use App\Models\InboxNotification;
use App\Models\Job;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IdentityTestCase;

class NotificationCenterTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.inbox_enabled' => true, 'notifications.business_enabled' => false]);
        Queue::fake();
    }

    public function test_inbox_is_independent_of_mail_and_read_state_is_owned_and_idempotent(): void
    {
        app(BusinessNotifications::class)->contractorApprovalChanged(User::find(1), User::find(2));
        $item = InboxNotification::firstOrFail();
        $this->assertSame(0, NotificationDelivery::count());
        Queue::assertNothingPushed();
        $this->signInAs(3);
        $this->post(route('notifications.read', $item))->assertNotFound();
        $this->get(route('notifications.index'))->assertOk()->assertViewHas('items', fn ($items) => $items->isEmpty());
        $this->signInAs(2);
        $this->get(route('notifications.index'))->assertOk()->assertSee('Account approval changes')->assertSee('1 unread')->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('notifications.read', $item))->assertRedirect();
        $readAt = $item->fresh()->read_at->toDateTimeString();
        $this->travel(1)->minutes();
        $this->post(route('notifications.read', $item))->assertRedirect();
        $this->assertSame($readAt, $item->fresh()->read_at->toDateTimeString());
        $this->get(route('notifications.index', ['unread' => 1]))->assertOk()->assertViewHas('items', fn ($items) => $items->isEmpty());
    }

    public function test_preferences_skip_future_and_already_queued_mail_but_keep_inbox_and_recovery(): void
    {
        config(['notifications.business_enabled' => true]);
        $messages = app(BusinessNotifications::class);
        $messages->contractorApprovalChanged(User::find(1), User::find(2));
        $queued = NotificationDelivery::firstOrFail();
        $this->signInAs(2);
        $this->put(route('notifications.preferences.update'), [])->assertRedirect()->assertSessionHasNoErrors();
        app(DeliverIdentityMessage::class)->run($queued->id);
        $this->assertSame('skipped', $queued->fresh()->status);
        $messages->contractorApprovalChanged(User::find(1), User::find(2));
        $this->assertSame(1, NotificationDelivery::count());
        $this->assertSame(2, InboxNotification::count());
        app(IdentityMessages::class)->recovery(User::find(2), 'synthetic-security-token');
        $this->assertSame(2, NotificationDelivery::count());
        $this->assertSame(2, InboxNotification::count());
        $this->get(route('notifications.index'))->assertDontSee('synthetic-security-token');
        $this->putJson(route('notifications.preferences.update'), ['email_events' => ['registration.created']])->assertUnprocessable();
        $this->putJson(route('notifications.preferences.update'), ['email_events' => ['email.verification']])->assertUnprocessable();
        $this->put(route('notifications.preferences.update'), ['email_events' => ['contractor.approval_changed'], 'user_id' => 3])->assertRedirect();
        $this->assertDatabaseMissing('app_notification_preferences', ['user_id' => 3]);
        $this->get(route('notifications.preferences'))->assertOk()->assertSee('Email preferences');
    }

    public function test_pending_and_unverified_accounts_cannot_access_inbox_or_preferences(): void
    {
        User::whereKey(2)->update(['is_approved' => 2]);
        $this->signInAs(2);
        $this->get(route('notifications.index'))->assertForbidden();
        $this->get(route('notifications.preferences'))->assertForbidden();
        $this->put(route('notifications.preferences.update'), [])->assertForbidden();
        $this->get(route('account.status'))->assertOk();
        User::whereKey(2)->update(['is_approved' => 1]);
        $this->unverify(2);
        $this->signInAs(2);
        $this->get(route('notifications.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_inbox_links_recheck_assignment_and_do_not_expose_private_payloads(): void
    {
        $job = Job::findOrFail(5);
        app(BusinessNotifications::class)->jobAssigned(User::find(1), $job);
        $this->signInAs(3);
        $this->get(route('notifications.index'))->assertOk()->assertSee(route('contractor.jobs.show', $job));
        $job->forceFill(['contractor_id' => 2])->save();
        $this->get(route('notifications.index'))->assertOk()->assertDontSee(route('contractor.jobs.show', $job))->assertSee('no longer available');
        $this->get(route('contractor.jobs.show', $job))->assertForbidden();
    }

    public function test_registration_inbox_does_not_require_email_and_rollback_keeps_no_inbox_or_intents(): void
    {
        config(['identity.registration_admin_mail' => false]);
        app(IdentityMessages::class)->registration(User::find(2));
        $this->assertSame(1, InboxNotification::count());
        $this->assertSame(0, NotificationDelivery::count());
        $this->signInAs(1);
        $this->get(route('notifications.index'))->assertSee('New contractor registrations')->assertSee(route('admin.contractors.show', 2));
        DB::beginTransaction();
        app(BusinessNotifications::class)->contractorApprovalChanged(User::find(1), User::find(2));
        DB::rollBack();
        $this->assertSame(1, InboxNotification::count());
        $this->assertSame(1, NotificationEvent::count());
    }

    public function test_failure_screen_is_admin_only_redacted_and_retry_rechecks_preferences(): void
    {
        config(['notifications.business_enabled' => true, 'mail.default' => 'smtp']);
        app(BusinessNotifications::class)->contractorApprovalChanged(User::find(1), User::find(2));
        $delivery = NotificationDelivery::firstOrFail();
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->signInAs(2);
        $this->get(route('admin.notification-deliveries.index'))->assertForbidden();
        $this->post(route('admin.notification-deliveries.retry', $delivery), ['confirmed' => 1])->assertForbidden();
        $this->put(route('notifications.preferences.update'), [])->assertRedirect();
        $this->signInAs(1);
        $this->get(route('admin.notification-deliveries.index'))->assertOk()->assertSee($delivery->id)->assertDontSee($delivery->recipient_email)->assertDontSee('RuntimeException');
        $this->postJson(route('admin.notification-deliveries.retry', $delivery), [])->assertUnprocessable();
        $this->post(route('admin.notification-deliveries.retry', $delivery), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        Queue::assertPushed(DeliverIdentityEvent::class, 1);
        Queue::assertPushed(DeliverNotificationDelivery::class, fn ($job) => $job->deliveryId === $delivery->id);
        $this->postJson(route('admin.notification-deliveries.retry', $delivery), ['confirmed' => 1])->assertUnprocessable();
        config(['mail.default' => 'array']);
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Notification::assertNothingSent();
    }
}
