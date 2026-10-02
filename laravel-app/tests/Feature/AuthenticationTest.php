<?php

namespace Tests\Feature;

use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class AuthenticationTest extends IdentityTestCase
{
    public function test_legacy_bcrypt_login_rotates_session_and_redirects_both_roles(): void
    {
        foreach ([1 => 'admin', 2 => 'contractor'] as $id => $role) {
            $user = User::findOrFail($id);
            $hash = $user->password_hash;
            $this->get('/login')->assertOk();
            $oldSession = session()->getId();
            $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'fixture-known-password'])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($oldSession, session()->getId());
            $this->assertSame(0, session('auth_session_version'));
            $this->assertSame($hash, $user->fresh()->password_hash);
            $this->get('/dashboard')->assertRedirect(route($role.'.dashboard'));
            $this->get('/'.$role)->assertOk();
            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_generic_login_failure_does_not_change_hash_or_flash_password(): void
    {
        $hash = User::findOrFail(2)->password_hash;
        foreach (['contractor@example.test', 'missing@example.test'] as $email) {
            $this->from('/login')->post('/login', ['email' => $email, 'password' => 'wrong-secret-fixture'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertArrayNotHasKey('password', session()->getOldInput());
        }
        $this->assertSame($hash, User::findOrFail(2)->password_hash);
    }

    public function test_pending_and_review_contractors_only_get_status_and_verification(): void
    {
        foreach ([0, 2] as $approval) {
            User::where('user_id', 2)->update(['is_approved' => $approval]);
            $this->signInAs(2);
            $this->get('/dashboard')->assertRedirect(route('account.status'));
            $this->get('/account/status')->assertOk()->assertSee('Administrator review');
            $this->get('/contractor')->assertForbidden();
            $this->get('/admin')->assertForbidden();
            $this->get('/admin/accounts')->assertForbidden();
        }
    }

    public function test_all_imported_roles_need_email_proof_before_workspace_access(): void
    {
        foreach ([1 => 'admin', 2 => 'contractor'] as $id => $role) {
            $this->unverify($id);
            $this->signInAs($id);
            $this->get('/dashboard')->assertRedirect(route('account.status'));
            $this->get('/'.$role)->assertRedirect(route('verification.notice'));
            $this->get('/account/status')->assertOk()->assertSee('Request verification email');
        }
    }

    public function test_request_refreshes_database_role_approval_and_session_version(): void
    {
        $this->signInAs(2);
        $this->get('/contractor')->assertOk();
        DB::table('Users')->where('user_id', 2)->update(['is_approved' => 2]);
        $this->get('/contractor')->assertForbidden();
        DB::table('Users')->where('user_id', 2)->update(['is_admin' => 1]);
        $this->get('/contractor')->assertForbidden();
        $this->get('/admin')->assertOk();
        AccountState::where('user_id', 2)->update(['session_version' => 1]);
        $this->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_blocked_archived_and_unknown_role_accounts_cannot_authenticate_or_recover(): void
    {
        foreach (['blocked_at', 'archived_at'] as $field) {
            AccountState::where('user_id', 2)->update([$field => now()]);
            $this->post('/login', ['email' => 'contractor@example.test', 'password' => 'fixture-known-password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->post('/forgot-password', ['email' => 'contractor@example.test'])->assertSessionHas('status');
            $this->assertSame(0, DB::table('app_password_reset_tokens')->count());
            AccountState::where('user_id', 2)->update([$field => null]);
        }
        User::where('user_id', 2)->update(['is_admin' => null]);
        $this->post('/login', ['email' => 'contractor@example.test', 'password' => 'fixture-known-password'])->assertSessionHasErrors('email');
    }

    public function test_login_rate_limit_and_logout_csrf_session_rotation(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'bad'])->assertStatus(429)->assertHeader('Retry-After');
        $this->signInAs(1);
        $token = session()->token();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
    }

    public function test_malicious_intended_redirect_is_discarded(): void
    {
        $this->withSession(['url.intended' => 'https://attacker.invalid/collect']);
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'fixture-known-password'])->assertRedirect(route('dashboard'));
    }

    public function test_password_rehash_only_occurs_after_valid_credentials(): void
    {
        config(['hashing.bcrypt.rounds' => 11]);
        $old = User::findOrFail(2)->password_hash;
        $this->post('/login', ['email' => 'contractor@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->assertSame($old, User::findOrFail(2)->password_hash);
        $this->post('/login', ['email' => 'contractor@example.test', 'password' => 'fixture-known-password'])->assertRedirect(route('dashboard'));
        $new = User::findOrFail(2)->password_hash;
        $this->assertNotSame($old, $new);
        $this->assertTrue(password_verify('fixture-known-password', $new));
        $this->assertSame(11, password_get_info($new)['options']['cost']);
    }

    public function test_long_compatible_legacy_password_still_logs_in_without_an_unsafe_rehash(): void
    {
        config(['hashing.bcrypt.rounds' => 11]);
        $password = str_repeat('legacy-passphrase-', 5);
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        User::where('user_id', 2)->update(['password_hash' => $hash]);
        $this->post('/login', ['email' => 'contractor@example.test', 'password' => $password])->assertRedirect(route('dashboard'));
        $this->assertSame($hash, User::findOrFail(2)->password_hash);
    }
}
