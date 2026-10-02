<?php

namespace Tests\Feature;

use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class AdminPasswordTest extends IdentityTestCase
{
    private function data(string $current = 'fixture-known-password'): array
    {
        return ['current_password' => $current, 'password' => 'admin-set-fixture-passphrase', 'password_confirmation' => 'admin-set-fixture-passphrase'];
    }

    public function test_admin_password_setting_requires_current_password_and_revokes_target_sessions(): void
    {
        $this->signInAs(1);
        $old = User::findOrFail(2)->password_hash;
        $this->get('/admin/accounts')->assertOk()->assertSee('Account access');
        $this->get('/admin/accounts/2/password')->assertOk();
        $this->post('/admin/accounts/2/password', $this->data('wrong-fixture-password'))->assertSessionHasErrors('current_password');
        $this->assertSame($old, User::findOrFail(2)->password_hash);
        $this->post('/admin/accounts/2/password', $this->data() + ['user_id' => 3])->assertRedirect(route('admin.accounts.index'));
        $target = User::findOrFail(2);
        $this->assertTrue(password_verify($this->data()['password'], $target->password_hash));
        $this->assertSame(1, $target->accountState->session_version);
        $this->assertSame(1, $target->is_approved);
        $this->assertTrue($target->hasVerifiedEmail());
        $this->assertTrue(password_verify('fixture-known-password', User::findOrFail(3)->password_hash));
        $log = DB::table('ActivityLogs')->orderByDesc('log_id')->first();
        $this->assertSame(1, $log->user_id);
        $this->assertStringContainsString('account 2', $log->action);
        $this->assertStringNotContainsString($this->data()['password'], $log->action);
    }

    public function test_guest_contractor_and_unverified_admin_cannot_read_or_submit_admin_password_routes(): void
    {
        $this->get('/admin/accounts/2/password')->assertRedirect(route('login'));
        $this->post('/admin/accounts/2/password', $this->data())->assertRedirect(route('login'));
        $hash = User::findOrFail(2)->password_hash;
        $this->signInAs(3);
        $this->get('/admin/accounts')->assertForbidden();
        $this->post('/admin/accounts/2/password', $this->data())->assertForbidden();
        $this->unverify(1);
        $this->signInAs(1);
        $this->post('/admin/accounts/2/password', $this->data())->assertRedirect(route('verification.notice'));
        $this->assertSame($hash, User::findOrFail(2)->password_hash);
    }

    public function test_admin_cannot_reset_disabled_account_and_self_reset_signs_out(): void
    {
        $this->signInAs(1);
        AccountState::where('user_id', 2)->update(['archived_at' => now()]);
        $this->post('/admin/accounts/2/password', $this->data())->assertForbidden();
        $this->post('/admin/accounts/1/password', $this->data())->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(1, User::findOrFail(1)->accountState->session_version);
    }
}
