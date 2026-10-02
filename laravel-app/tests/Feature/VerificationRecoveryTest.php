<?php

namespace Tests\Feature;

use App\Models\AccountState;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Notifications\IdentityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\IdentityTestCase;

class VerificationRecoveryTest extends IdentityTestCase
{
    private function verifyUrl(User $user, bool $expired = false): string
    {
        return URL::temporarySignedRoute('verification.verify', $expired ? now()->subMinute() : now()->addHour(), ['id' => $user->user_id, 'hash' => sha1($user->email)]);
    }

    public function test_verification_is_signed_scoped_expiring_and_does_not_approve_pending_account(): void
    {
        User::where('user_id', 2)->update(['is_approved' => 0]);
        $user = $this->unverify(2);
        $this->signInAs(2);
        $this->get($this->verifyUrl(User::findOrFail(3)))->assertForbidden();
        $this->get($this->verifyUrl($user, true))->assertForbidden();
        $url = $this->verifyUrl($user);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame(0, $user->fresh()->is_approved);
        $this->get('/contractor')->assertForbidden();
        $count = DB::table('ActivityLogs')->count();
        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertSame($count, DB::table('ActivityLogs')->count());
    }

    public function test_email_change_revokes_previous_proof_and_old_signed_link(): void
    {
        $user = User::findOrFail(2);
        $oldUrl = $this->verifyUrl($user);
        $this->signInAs(2);
        User::where('user_id', 2)->update(['email' => 'changed@example.test']);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get('/contractor')->assertRedirect(route('verification.notice'));
        $this->get($oldUrl)->assertForbidden();
        $this->assertSame(1, $user->fresh()->is_approved);
    }

    public function test_verification_resend_is_throttled_and_link_payload_is_encrypted(): void
    {
        $user = $this->unverify(2);
        $this->signInAs(2);
        $this->post('/email/verification-notification')->assertRedirect(route('account.status'));
        $this->post('/email/verification-notification')->assertStatus(429);
        $this->assertSame(1, NotificationEvent::count());
        $event = NotificationEvent::firstOrFail();
        $raw = DB::table('app_notification_events')->value('payload');
        $this->assertStringNotContainsString($event->payload['url'], $raw);
        $this->assertArrayNotHasKey('payload', $event->toArray());
        Notification::assertSentTo($user, IdentityNotification::class);
    }

    public function test_recovery_response_is_generic_and_unverified_accounts_can_receive_security_mail(): void
    {
        $user = $this->unverify(2);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $status = session('status');
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertSessionHas('status', $status);
        Notification::assertSentTo($user, IdentityNotification::class, fn ($notification) => $notification->type === 'password.recovery');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_recovery_token_is_hashed_single_use_and_revokes_existing_sessions_without_approval_changes(): void
    {
        $this->post('/forgot-password', ['email' => 'contractor@example.test'])->assertSessionHas('status');
        $event = NotificationEvent::where('type', 'password.recovery')->firstOrFail();
        $token = basename(parse_url($event->payload['url'], PHP_URL_PATH));
        $stored = DB::table('app_password_reset_tokens')->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(password_verify($token, $stored));
        $data = ['email' => 'contractor@example.test', 'token' => $token, 'password' => 'new-recovery-passphrase', 'password_confirmation' => 'new-recovery-passphrase'];
        $this->post('/reset-password', $data)->assertRedirect(route('login'));
        $user = User::findOrFail(2);
        $this->assertTrue(password_verify($data['password'], $user->password_hash));
        $this->assertSame(1, $user->accountState->session_version);
        $this->assertSame(1, $user->is_approved);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(0, DB::table('app_password_reset_tokens')->count());
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->actingAs($user)->withSession(['auth_session_version' => 0])->get('/contractor')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_expired_token_and_blocked_account_cannot_reset_password(): void
    {
        $this->post('/forgot-password', ['email' => 'contractor@example.test']);
        $event = NotificationEvent::where('type', 'password.recovery')->firstOrFail();
        $token = basename(parse_url($event->payload['url'], PHP_URL_PATH));
        $hash = User::findOrFail(2)->password_hash;
        DB::table('app_password_reset_tokens')->update(['created_at' => now()->subMinutes(61)]);
        $data = ['email' => 'contractor@example.test', 'token' => $token, 'password' => 'expired-fixture-passphrase', 'password_confirmation' => 'expired-fixture-passphrase'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertSame($hash, User::findOrFail(2)->password_hash);
        DB::table('app_password_reset_tokens')->update(['created_at' => now()]);
        AccountState::where('user_id', 2)->update(['blocked_at' => now()]);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertSame($hash, User::findOrFail(2)->password_hash);
    }
}
