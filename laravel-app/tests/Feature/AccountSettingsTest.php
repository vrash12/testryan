<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\IdentityTestCase;

class AccountSettingsTest extends IdentityTestCase
{
    public function test_settings_use_the_workspace_and_only_link_the_signed_in_profile(): void
    {
        $this->signInAs(2);
        $this->get('/account/settings')->assertOk()->assertViewIs('auth.account-settings')
            ->assertSee(route('contractor.profile.edit', 2), false)
            ->assertDontSee(route('contractor.profile.edit', 3), false)
            ->assertSee('Email preferences')->assertDontSee('aria-label="Account status"', false);
        $this->get('/account/status')->assertOk()->assertViewIs('auth.account-settings');
        $this->signInAs(1);
        $this->get('/account/settings')->assertOk()->assertViewIs('auth.account-settings')
            ->assertDontSee('Edit profile')->assertSee('Administrator access');
    }

    public function test_pending_and_unverified_accounts_retain_the_status_gate(): void
    {
        $this->signInAs(2);
        User::whereKey(2)->update(['is_approved' => 2]);
        $this->signInAs(2);
        $this->get('/account/settings')->assertRedirect(route('account.status'));
        $this->get('/account/status')->assertOk()->assertViewIs('auth.account-status')->assertDontSee('Manage email updates');
        $this->unverify(1);
        $this->signInAs(1);
        $this->get('/account/settings')->assertRedirect(route('account.status'));
    }
}
