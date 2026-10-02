<?php

namespace Tests\Feature;

use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class ContractorBlockingTest extends IdentityTestCase
{
    public function test_admin_block_revokes_access_without_deleting_data_and_unblock_requires_review(): void
    {
        $this->signInAs(1);
        $jobs = DB::table('Jobs')->count();
        $this->post(route('admin.contractors.block', 2), ['blocked' => 1, 'reason' => 'Synthetic review reason', 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $state = AccountState::findOrFail(2);
        $this->assertNotNull($state->blocked_at);
        $this->assertSame(1, $state->session_version);
        $this->assertSame($jobs, DB::table('Jobs')->count());
        $this->get(route('admin.contractors.index', ['blocked' => 1]))->assertOk()->assertSee('Account access blocked');
        $this->signInAs(2);
        $this->get(route('contractor.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->signInAs(1);
        $this->post(route('admin.contractors.block', 2), ['blocked' => 0, 'reason' => 'Resolved', 'confirmed' => 1])->assertRedirect();
        $this->assertNull($state->fresh()->blocked_at);
        $this->assertSame(2, User::find(2)->is_approved);
        $this->signInAs(2);
        $this->get(route('contractor.dashboard'))->assertForbidden();
        $this->get(route('account.status'))->assertOk();
    }

    public function test_block_controls_reject_nonadmins_admin_targets_and_unconfirmed_or_reasonless_requests(): void
    {
        $this->signInAs(2);
        $this->post(route('admin.contractors.block', 3), ['blocked' => 1, 'reason' => 'No', 'confirmed' => 1])->assertForbidden();
        $this->signInAs(1);
        $this->post(route('admin.contractors.block', 1), ['blocked' => 1, 'reason' => 'No', 'confirmed' => 1])->assertForbidden();
        $this->postJson(route('admin.contractors.block', 2), ['blocked' => 1, 'confirmed' => 1])->assertUnprocessable();
        $this->postJson(route('admin.contractors.block', 2), ['blocked' => 1, 'reason' => 'No'])->assertUnprocessable();
        $this->assertNull(AccountState::find(2)->blocked_at);
    }

    public function test_bulk_block_is_atomic_and_requires_pending_selection_and_reason(): void
    {
        User::whereKey(2)->update(['is_approved' => 0]);
        $this->signInAs(1);
        $payload = ['action' => 'block', 'contractor_ids' => [2, 3], 'reason' => 'Manual review', 'confirmed' => 1];
        $this->postJson(route('admin.review.bulk'), $payload)->assertUnprocessable();
        $this->assertNull(AccountState::find(2)->blocked_at);
        User::whereKey(3)->update(['is_approved' => 2]);
        $this->postJson(route('admin.review.bulk'), array_replace($payload, ['reason' => '']))->assertUnprocessable();
        $this->post(route('admin.review.bulk'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull(AccountState::find(2)->blocked_at);
        $this->assertNotNull(AccountState::find(3)->blocked_at);
        $this->get(route('admin.review.index'))->assertDontSee('contractor@example.test');
    }
}
