<?php

namespace Tests\Feature;

use App\Actions\Contractor\ContractorManagement;
use App\Actions\Identity\DeliverIdentityMessage;
use App\Actions\Job\JobManagement;
use App\Actions\Notifications\BusinessNotifications;
use App\Enums\MediaTag;
use App\Jobs\DeliverIdentityEvent;
use App\Models\AccountState;
use App\Models\ContractorDocument;
use App\Models\Job;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Notifications\BusinessNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class M6NotificationsTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.business_enabled' => true]);
        Queue::fake();
    }

    public function test_master_and_event_switches_control_intent_creation_and_delivery(): void
    {
        $admin = User::findOrFail(1);
        $contractor = User::findOrFail(2);
        $notifications = app(BusinessNotifications::class);
        config(['notifications.business_enabled' => false]);
        $notifications->contractorApprovalChanged($admin, $contractor);
        $this->assertSame(0, NotificationEvent::count());
        config(['notifications.business_enabled' => true, 'notifications.events.contractor.approval_changed' => false]);
        $notifications->contractorApprovalChanged($admin, $contractor);
        $this->assertSame(0, NotificationEvent::count());
        Queue::assertNothingPushed();

        config(['notifications.events.contractor.approval_changed' => true]);
        $notifications->contractorApprovalChanged($admin, $contractor);
        $delivery = NotificationDelivery::firstOrFail();
        Queue::assertPushed(DeliverIdentityEvent::class, 1);
        config(['notifications.events.contractor.approval_changed' => false]);
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Notification::assertNothingSent();

        config(['notifications.events.contractor.approval_changed' => true]);
        $notifications->contractorApprovalChanged($admin, $contractor);
        $newDelivery = NotificationDelivery::where('status', 'pending')->firstOrFail();
        config(['notifications.business_enabled' => false]);
        app(DeliverIdentityMessage::class)->run($newDelivery->id);
        $this->assertSame('skipped', $newDelivery->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_meaningful_job_transitions_notify_once_and_new_episodes_do_not_collide_at_the_same_instant(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = User::findOrFail(1);
        $contractor = User::findOrFail(2);
        $management = app(JobManagement::class);
        $job = $management->create($admin, $this->jobData());
        $this->assertSame(1, NotificationEvent::where('type', 'job.assigned')->count());
        $management->assign($admin, $job, 2);
        $management->update($admin, $job, $this->jobData());
        $this->assertSame(1, NotificationEvent::count());
        $job = $management->update($admin, $job, $this->jobData(['job_name' => 'Changed synthetic job']));
        $this->assertSame(1, NotificationEvent::where('type', 'job.updated')->count());
        $job = $management->accept($contractor, $job);
        $management->accept($contractor, $job);
        $this->assertSame(1, NotificationEvent::where('type', 'job.accepted')->count());
        $job = $management->complete($admin, $job, true);
        $management->complete($admin, $job, true);
        $this->assertSame(1, NotificationEvent::where('type', 'job.completed')->count());
        $job = $management->complete($admin, $job, false);
        $management->complete($admin, $job, true);
        $this->assertSame(2, NotificationEvent::where('type', 'job.completed')->count());
        $this->assertSame(5, NotificationEvent::count());
        $this->assertSame(5, NotificationDelivery::count());
        Queue::assertPushed(DeliverIdentityEvent::class, 5);
        Notification::assertNothingSent();
        $this->travelBack();
    }

    public function test_rolled_back_workflow_does_not_commit_or_dispatch_a_notification(): void
    {
        $admin = User::findOrFail(1);
        try {
            DB::transaction(function () use ($admin): void {
                app(JobManagement::class)->create($admin, $this->jobData(['job_name' => 'Rolled back job']));
                throw new RuntimeException('Synthetic workflow rollback');
            });
            $this->fail('The synthetic transaction should have rolled back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic workflow rollback', $exception->getMessage());
        }
        $this->assertFalse(Job::where('job_name', 'Rolled back job')->exists());
        $this->assertSame(0, NotificationEvent::count());
        $this->assertSame(0, NotificationDelivery::count());
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_reassignment_and_profile_review_revoke_queued_job_mail(): void
    {
        $admin = User::findOrFail(1);
        $management = app(JobManagement::class);
        $job = $management->create($admin, $this->jobData());
        $previous = NotificationDelivery::firstOrFail();
        $job = $management->assign($admin, $job, 3);
        app(DeliverIdentityMessage::class)->run($previous->id);
        $this->assertSame('skipped', $previous->fresh()->status);
        Notification::assertNothingSent();
        $current = NotificationDelivery::where('recipient_user_id', 3)->firstOrFail();
        app(DeliverIdentityMessage::class)->run($current->id);
        Notification::assertSentTo(User::findOrFail(3), BusinessNotification::class, function (BusinessNotification $notification) use ($job): bool {
            return $notification->type === 'job.assigned'
                && $notification->toMail(User::findOrFail(3))->actionUrl === route('contractor.jobs.show', $job);
        });

        $job = $management->assign($admin, $job, 2);
        $reviewedDelivery = NotificationDelivery::where('recipient_user_id', 2)->where('status', 'pending')->firstOrFail();
        $contractor = User::findOrFail(2);
        app(ContractorManagement::class)->update($contractor, $contractor, ['company_name' => 'Profile now awaiting review']);
        $this->assertSame(2, $contractor->fresh()->is_approved);
        app(DeliverIdentityMessage::class)->run($reviewedDelivery->id);
        $this->assertSame('skipped', $reviewedDelivery->fresh()->status);
        Notification::assertNotSentTo($contractor, BusinessNotification::class);
    }

    public function test_approval_mail_remains_available_to_verified_pending_contractors_and_profile_mail_has_no_private_content(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = User::findOrFail(1);
        $contractor = User::findOrFail(2);
        $management = app(ContractorManagement::class);
        $contractor = $management->update($contractor, $contractor, ['company_name' => 'Synthetic private business name']);
        $management->update($contractor, $contractor, ['company_name' => 'Synthetic private business name']);
        $this->assertSame(1, NotificationEvent::where('type', 'contractor.profile_changed')->count());
        $profileEvent = NotificationEvent::where('type', 'contractor.profile_changed')->firstOrFail();
        $this->assertArrayNotHasKey('company_name', $profileEvent->payload);
        $this->assertStringNotContainsString('Synthetic private business name', DB::table('app_notification_events')->where('id', $profileEvent->id)->value('payload'));
        $this->assertArrayNotHasKey('payload', $profileEvent->toArray());
        app(DeliverIdentityMessage::class)->run($profileEvent->deliveries()->firstOrFail()->id);
        Notification::assertSentTo($admin, BusinessNotification::class, fn (BusinessNotification $notification): bool => $notification->type === 'contractor.profile_changed');

        $contractor = $management->setApproval($admin, $contractor, 0);
        $management->setApproval($admin, $contractor, 0);
        $this->assertFalse($contractor->canAccessWork());
        $this->assertSame(1, NotificationEvent::where('type', 'contractor.approval_changed')->count());
        $event = NotificationEvent::where('type', 'contractor.approval_changed')->firstOrFail();
        app(DeliverIdentityMessage::class)->run($event->deliveries()->firstOrFail()->id);
        Notification::assertSentTo($contractor, BusinessNotification::class, fn (BusinessNotification $notification): bool => $notification->type === 'contractor.approval_changed' && $notification->toMail($contractor)->actionUrl === route('account.status'));
        $this->travelBack();
    }

    public function test_notes_and_upload_batches_create_generic_counterpart_mail_without_private_text_or_files(): void
    {
        Storage::fake('private');
        $admin = User::findOrFail(1);
        $contractor = User::findOrFail(2);
        $management = app(JobManagement::class);
        $job = $management->create($admin, $this->jobData());
        $job = $management->accept($contractor, $job);
        $management->addNote($admin, $job, 'Synthetic confidential note text');
        $management->addMedia($contractor, $job, [
            UploadedFile::fake()->createWithContent('private-one.pdf', "%PDF-1.4\n%%EOF\n"),
            UploadedFile::fake()->createWithContent('private-two.pdf', "%PDF-1.4\n%%EOF\n"),
        ], MediaTag::During);
        $this->assertSame(1, NotificationEvent::where('type', 'job.note_added')->count());
        $this->assertSame(1, NotificationEvent::where('type', 'job.media_uploaded')->count());
        $note = NotificationEvent::where('type', 'job.note_added')->firstOrFail();
        $media = NotificationEvent::where('type', 'job.media_uploaded')->firstOrFail();
        $this->assertArrayNotHasKey('note_text', $note->payload);
        $this->assertSame(2, $media->payload['count']);
        $this->assertArrayNotHasKey('file_data', $media->payload);
        $this->assertSame([2], $note->deliveries()->pluck('recipient_user_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame([1], $media->deliveries()->pluck('recipient_user_id')->map(fn ($id): int => (int) $id)->all());
        app(DeliverIdentityMessage::class)->run($note->deliveries()->firstOrFail()->id);
        app(DeliverIdentityMessage::class)->run($media->deliveries()->firstOrFail()->id);
        Notification::assertSentTo($contractor, BusinessNotification::class, function (BusinessNotification $notification) use ($contractor): bool {
            return $notification->type === 'job.note_added'
                && ! str_contains(implode(' ', $notification->toMail($contractor)->introLines), 'Synthetic confidential note text');
        });
        Notification::assertSentTo($admin, BusinessNotification::class, function (BusinessNotification $notification) use ($admin, $job): bool {
            return $notification->type === 'job.media_uploaded'
                && $notification->toMail($admin)->actionUrl === route('admin.jobs.show', $job)
                && $notification->toMail($admin)->attachments === [];
        });
    }

    public function test_failed_delivery_is_private_retryable_and_success_does_not_send_twice(): void
    {
        $job = app(JobManagement::class)->create(User::findOrFail(1), $this->jobData());
        $delivery = NotificationDelivery::firstOrFail();
        Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('Synthetic SMTP password must stay private'));
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(RuntimeException::class, $delivery->last_error);
        $this->assertArrayNotHasKey('last_error', $delivery->toArray());
        $this->assertArrayNotHasKey('recipient_email', $delivery->toArray());
        $this->assertStringNotContainsString('Synthetic SMTP password', $delivery->last_error);
        Notification::fake();
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame(1, $delivery->fresh()->attempts);
        Notification::assertNothingSent();
        $this->travel(10)->minutes();
        $this->artisan('nestcrafters:retry-identity-mail')->assertSuccessful();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->artisan('nestcrafters:retry-identity-mail')->assertSuccessful();
        app(DeliverIdentityMessage::class)->run($delivery->id);
        Notification::assertSentToTimes(User::findOrFail(2), BusinessNotification::class, 1);
        $this->assertSame(1, NotificationEvent::where('type', 'job.assigned')->count());
        $this->assertSame($job->job_id, $delivery->event->payload['job_id']);
        $this->travelBack();
    }

    public function test_business_mail_renders_into_the_local_array_sink_without_private_job_content(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'local@nestcrafters.test', 'mail.from.name' => 'NestCrafters local test']);
        Notification::swap(new ChannelManager($this->app));
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $transport->flush();
        $job = app(JobManagement::class)->create(User::findOrFail(1), $this->jobData());
        $delivery = NotificationDelivery::firstOrFail();
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame('A job was assigned to you', $message->getSubject());
        $this->assertSame('contractor@example.test', $message->getTo()[0]->getAddress());
        $body = $message->getHtmlBody().' '.$message->getTextBody();
        $this->assertStringContainsString(route('contractor.jobs.show', $job), $body);
        $this->assertStringNotContainsString('Synthetic private job description', $body);
        $this->assertStringNotContainsString('fixture-known-password', $body);
        $this->assertCount(0, $message->getAttachments());
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertCount(1, $transport->messages());
    }

    public function test_external_mail_is_blocked_locally_and_recipient_email_or_archive_changes_skip_delivery(): void
    {
        $notifications = app(BusinessNotifications::class);
        $admin = User::findOrFail(1);
        $contractor = User::findOrFail(2);
        $notifications->contractorApprovalChanged($admin, $contractor);
        $external = NotificationDelivery::firstOrFail();
        config(['mail.default' => 'smtp']);
        app(DeliverIdentityMessage::class)->run($external->id);
        $this->assertSame('failed', $external->fresh()->status);
        Notification::assertNothingSent();
        config(['mail.default' => 'array']);

        $notifications->contractorApprovalChanged($admin, $contractor);
        $emailChanged = NotificationDelivery::where('status', 'pending')->firstOrFail();
        User::where('user_id', 2)->update(['email' => 'changed-mail@example.test']);
        app(DeliverIdentityMessage::class)->run($emailChanged->id);
        $this->assertSame('skipped', $emailChanged->fresh()->status);
        User::where('user_id', 2)->update(['email' => 'contractor@example.test']);

        $notifications->contractorApprovalChanged($admin, User::findOrFail(2));
        $archived = NotificationDelivery::where('status', 'pending')->firstOrFail();
        AccountState::where('user_id', 2)->update(['archived_at' => now()]);
        app(DeliverIdentityMessage::class)->run($archived->id);
        $this->assertSame('skipped', $archived->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_expiry_reminders_include_both_window_boundaries_and_deduplicate_only_active_contractor_insurance(): void
    {
        $this->travelTo(now()->startOfDay());
        $today = today()->toDateString();
        $this->document(['file_name' => 'today.pdf', 'expiry_date' => $today]);
        $this->document(['file_name' => 'inclusive-thirty.pdf', 'expiry_date' => today()->addDays(30)->toDateString()]);
        $this->document(['file_name' => 'already-expired.pdf', 'expiry_date' => today()->subDay()->toDateString()]);
        $this->document(['file_name' => 'too-late.pdf', 'expiry_date' => today()->addDays(31)->toDateString()]);
        $this->document(['file_name' => 'archived.pdf', 'expiry_date' => $today, 'archived' => 1]);
        $this->document(['file_name' => 'w9.pdf', 'expiry_date' => $today, 'file_type' => 'W9']);
        $this->document(['file_name' => 'admin.pdf', 'expiry_date' => $today, 'user_id' => 1]);
        $this->document(['file_name' => 'blocked.pdf', 'expiry_date' => $today, 'user_id' => 3]);
        AccountState::where('user_id', 3)->update(['blocked_at' => now()]);
        $this->artisan('nestcrafters:notify-document-expiry', ['--date' => $today])->assertSuccessful();
        $this->artisan('nestcrafters:notify-document-expiry', ['--date' => $today])->assertSuccessful();
        $events = NotificationEvent::where('type', 'document.expiring')->get();
        $this->assertCount(2, $events);
        $this->assertSame(2, NotificationDelivery::count());
        foreach ($events as $event) {
            $this->assertSame([1], $event->deliveries()->pluck('recipient_user_id')->map(fn ($id): int => (int) $id)->all());
            app(DeliverIdentityMessage::class)->run($event->deliveries()->firstOrFail()->id);
        }
        Notification::assertSentToTimes(User::findOrFail(1), BusinessNotification::class, 2);
        $this->assertSame(2, NotificationDelivery::where('status', 'delivered')->count());
        Queue::assertPushed(DeliverIdentityEvent::class, 2);
        $this->travelBack();
    }

    public function test_document_eligibility_is_rechecked_when_queued_reminders_are_delivered(): void
    {
        $date = today()->addDays(5)->toDateString();
        $archived = $this->document(['expiry_date' => $date]);
        $changedType = $this->document(['expiry_date' => $date]);
        $changedDate = $this->document(['expiry_date' => $date]);
        $blockedOwner = $this->document(['expiry_date' => $date, 'user_id' => 3]);
        $this->artisan('nestcrafters:notify-document-expiry')->assertSuccessful();
        $this->assertSame(4, NotificationEvent::where('type', 'document.expiring')->count());
        $archived->forceFill(['archived' => 1])->save();
        $changedType->forceFill(['file_type' => 'W9'])->save();
        $changedDate->forceFill(['expiry_date' => today()->addDays(31)->toDateString()])->save();
        AccountState::where('user_id', $blockedOwner->user_id)->update(['blocked_at' => now()]);
        foreach (NotificationDelivery::all() as $delivery) {
            app(DeliverIdentityMessage::class)->run($delivery->id);
            $this->assertSame('skipped', $delivery->fresh()->status);
        }
        Notification::assertNothingSent();
    }

    public function test_invalid_reminder_dates_fail_without_creating_intents(): void
    {
        foreach (['2026-02-30', 'next Thursday', '2026-1-1', today()->addDays(31)->toDateString(), today()->subDay()->toDateString()] as $date) {
            $this->artisan('nestcrafters:notify-document-expiry', ['--date' => $date])->assertFailed();
        }
        $this->assertSame(0, NotificationEvent::count());
        Queue::assertNothingPushed();
    }

    public function test_note_rate_limit_rejects_the_next_write_before_note_or_notification_creation(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->signInAs(3);
        $job = Job::findOrFail(5);
        $initialNotes = DB::table('Job_Notes')->where('job_id', $job->job_id)->count();
        $initialLogs = DB::table('ActivityLogs')->count();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->post(route('contractor.jobs.notes.store', $job), ['note_text' => 'Rate-limit fixture '.$attempt])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame($initialNotes + 30, DB::table('Job_Notes')->where('job_id', $job->job_id)->count());
        $this->assertSame($initialLogs + 30, DB::table('ActivityLogs')->count());
        $this->assertSame(30, NotificationEvent::where('type', 'job.note_added')->count());
        $this->assertSame(30, NotificationDelivery::count());

        $this->postJson(route('contractor.jobs.notes.store', $job), ['note_text' => 'Rejected beyond rate limit'])
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertSame($initialNotes + 30, DB::table('Job_Notes')->where('job_id', $job->job_id)->count());
        $this->assertSame($initialLogs + 30, DB::table('ActivityLogs')->count());
        $this->assertSame(30, NotificationEvent::where('type', 'job.note_added')->count());
        $this->assertSame(30, NotificationDelivery::count());
        $this->assertDatabaseMissing('Job_Notes', ['note_text' => 'Rejected beyond rate limit']);
        Queue::assertPushed(DeliverIdentityEvent::class, 30);
        Notification::assertNothingSent();
        $this->travelBack();
    }

    private function jobData(array $overrides = []): array
    {
        return array_replace([
            'job_name' => 'M6 synthetic job',
            'job_description' => '<p>Synthetic private job description</p>',
            'terms' => null,
            'due_date' => today()->addDays(7)->toDateString(),
            'approved_price' => '100.00',
            'deposit_amount' => '25.00',
            'who_supplies_materials' => 'Contractor',
            'services' => [3],
            'contractor_id' => 2,
        ], $overrides);
    }

    private function document(array $overrides = []): ContractorDocument
    {
        $document = new ContractorDocument;
        $document->forceFill(array_replace([
            'user_id' => 2,
            'unique_file_id' => Str::random(32),
            'file_type' => 'Insurance',
            'file_name' => 'synthetic-insurance.pdf',
            'file_data' => "%PDF-1.4\n%%EOF\n",
            'mime_type' => 'application/pdf',
            'upload_date' => now(),
            'expiry_date' => today()->addDays(10)->toDateString(),
            'archived' => 0,
        ], $overrides))->save();

        return $document;
    }
}
