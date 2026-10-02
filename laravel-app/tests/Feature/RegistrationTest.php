<?php

namespace Tests\Feature;

use App\Actions\Identity\IdentityMessages;
use App\Actions\Identity\RegisterContractor;
use App\Models\AccountState;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Notifications\IdentityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class RegistrationTest extends IdentityTestCase
{
    public function test_registration_creates_pending_contractor_services_and_durable_messages(): void
    {
        $data = $this->registration(['email' => ' NEW@example.test ']);
        $this->post('/register', $data)->assertRedirect(route('account.status'));
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->is_admin);
        $this->assertSame(0, $user->is_approved);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(password_verify($data['password'], $user->password_hash));
        $this->assertSame([3], $user->services()->pluck('Services.service_id')->all());
        $this->assertSame('', $user->phone2);
        $this->assertNull($user->approved_date);
        $this->assertSame(2, NotificationEvent::count());
        Notification::assertSentTo($user, IdentityNotification::class, fn ($notification) => $notification->type === 'email.verification');
        Notification::assertSentTo(User::findOrFail(1), IdentityNotification::class, fn ($notification) => $notification->type === 'registration.created');
        $this->get('/account/status')->assertOk()->assertSee('awaiting administrator approval');
        $this->get('/contractor')->assertForbidden();
    }

    public function test_registration_rejects_role_owner_approval_and_invalid_service_injection(): void
    {
        $this->post('/register', $this->registration(['is_admin' => 1, 'is_approved' => 1, 'user_id' => 1, 'services' => [999]]))->assertSessionHasErrors(['is_admin', 'is_approved', 'user_id', 'services.0']);
        $this->assertSame(3, User::count());
        $this->assertSame(0, NotificationEvent::count());
        Notification::assertNothingSent();
    }

    public function test_email_duplicate_normalization_and_company_signal_do_not_delete_accounts(): void
    {
        $this->post('/register', $this->registration(['email' => ' ADMIN@EXAMPLE.TEST ']))->assertSessionHasErrors('email');
        $company = User::findOrFail(1)->company_name;
        $this->post('/register', $this->registration(['company_name' => $company]))->assertRedirect(route('account.status'));
        $new = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertContains('matching_company_name', $new->accountState->registration_signals);
        $this->assertSame(0, $new->is_approved);
        $this->assertSame(4, User::count());
    }

    public function test_honeypot_wrong_answer_expiry_and_fast_submission_fail_before_creation(): void
    {
        foreach (['honeypot', 'answer', 'expired', 'fast'] as $i => $case) {
            $data = $this->registration(['email' => 'bot'.$i.'@example.test']);
            if ($case === 'honeypot') {
                $data['website'] = 'spam';
            }
            if ($case === 'answer') {
                $data['bot_answer'] = 'wrong';
            }
            if (in_array($case, ['expired', 'fast'], true)) {
                $this->withSession(['registration_challenge' => ['token' => $data['bot_token'], 'answer' => '12', 'issued_at' => $case === 'expired' ? now()->subMinutes(16)->timestamp : now()->timestamp]]);
            }
            $this->post('/register', $data)->assertSessionHasErrors('bot_answer');
            $this->assertSame(3, User::count());
        }
        Notification::assertNothingSent();
    }

    public function test_provider_fails_closed_outside_local_and_registration_can_be_disabled(): void
    {
        $data = $this->registration();
        $data['_token'] = session()->token();
        $this->app->instance('env', 'production');
        $this->post('/register', $data)->assertSessionHasErrors('bot_answer');
        $this->assertSame(3, User::count());
        config(['identity.registration_enabled' => false]);
        $this->get('/register')->assertNotFound();
        $this->post('/register', $data)->assertForbidden();
    }

    public function test_signup_transaction_rolls_back_user_services_audit_and_verification_intent(): void
    {
        $before = DB::table('ActivityLogs')->count();
        Queue::fake();
        app()->instance(IdentityMessages::class, new class extends IdentityMessages
        {
            public function registration(User $user): void
            {
                throw new RuntimeException('Synthetic failure after verification intent');
            }
        });
        try {
            app(RegisterContractor::class)->run($this->registration(), '127.0.0.1');
            $this->fail('The incomplete registration must roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure after verification intent', $exception->getMessage());
        }
        $this->assertSame(3, User::count());
        $this->assertSame(3, AccountState::count());
        $this->assertSame(1, DB::table('ContractorServices')->count());
        $this->assertSame($before, DB::table('ActivityLogs')->count());
        $this->assertSame(0, NotificationEvent::count());
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
