<?php

namespace Tests\Feature;

use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class M6ReviewTest extends IdentityTestCase
{
    public function test_review_queue_filters_pending_active_contractors_by_all_selected_services_and_escapes_private_indicators(): void
    {
        $this->pending(2, 2);
        $this->pending(3, 0);
        DB::table('Users')->where('user_id', 2)->update(['priority' => 1]);
        DB::table('Services')->insert(['service_id' => 4, 'service_name' => 'Second fixture service']);
        DB::table('ContractorServices')->insert([['user_id' => 2, 'service_id' => 4], ['user_id' => 3, 'service_id' => 3]]);
        $this->addContractor(4, ['priority' => 1]);
        $this->addContractor(5, ['priority' => 1]);
        DB::table('ContractorServices')->insert([
            ['user_id' => 4, 'service_id' => 3], ['user_id' => 4, 'service_id' => 4],
            ['user_id' => 5, 'service_id' => 3], ['user_id' => 5, 'service_id' => 4],
        ]);
        AccountState::where('user_id', 4)->update(['blocked_at' => now()]);
        AccountState::where('user_id', 5)->update(['archived_at' => now()]);
        AccountState::where('user_id', 2)->update(['registration_signals' => json_encode(['matching_company_name', '<script>alert(1)</script>'])]);
        $this->signInAs(1);

        $this->get(route('admin.review.index', ['q' => 'Synthetic', 'approval' => 2, 'priority' => 1, 'service_ids' => [3, 4]]))
            ->assertOk()
            ->assertViewHas('contractors', fn ($contractors) => $contractors->total() === 1 && (int) $contractors->first()->user_id === 2)
            ->assertSee('matching_company_name')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.review.index'))->assertOk()->assertViewHas('contractors', fn ($contractors) => $contractors->pluck('user_id')->sort()->values()->all() === [2, 3]);
    }

    public function test_bulk_approval_changes_only_explicit_selection_and_does_not_bypass_email_verification(): void
    {
        $this->pending(2, 2);
        $this->pending(3, 0);
        $this->unverify(2);
        $this->signInAs(1);

        $this->post(route('admin.review.bulk'), ['action' => 'approve', 'contractor_ids' => [2], 'confirmed' => '1'])
            ->assertRedirect(route('admin.review.index'))->assertSessionHasNoErrors();

        $approved = User::findOrFail(2);
        $this->assertSame(1, $approved->is_approved);
        $this->assertNotNull($approved->approved_date);
        $this->assertFalse($approved->hasVerifiedEmail());
        $this->assertFalse($approved->canAccessWork());
        $this->assertSame(1, $approved->accountState->session_version);
        $this->assertSame(0, User::findOrFail(3)->is_approved);
        $this->assertDatabaseHas('ActivityLogs', ['user_id' => 1, 'action' => 'Security: changed contractor approval to 1. (account #2)']);
    }

    public function test_bulk_archive_preserves_contractor_job_document_and_service_history(): void
    {
        $this->pending(2, 2);
        $this->pending(3, 0);
        $before = array_map(fn ($table) => DB::table($table)->count(), ['Users', 'Jobs', 'Files', 'ContractorServices']);
        $this->signInAs(1);

        $this->post(route('admin.review.bulk'), ['action' => 'archive', 'contractor_ids' => [2, 3], 'confirmed' => '1'])
            ->assertRedirect(route('admin.review.index'))->assertSessionHasNoErrors();

        $this->assertSame($before, array_map(fn ($table) => DB::table($table)->count(), ['Users', 'Jobs', 'Files', 'ContractorServices']));
        foreach ([2, 3] as $id) {
            $this->assertNotNull(AccountState::findOrFail($id)->archived_at);
            $this->assertSame(1, AccountState::findOrFail($id)->session_version);
            $this->assertFalse(User::findOrFail($id)->isActiveAccount());
        }
        $this->get(route('admin.review.index'))->assertOk()->assertViewHas('contractors', fn ($contractors) => $contractors->isEmpty());
    }

    public function test_bulk_rejection_retains_active_account_with_not_approved_state_and_private_reason(): void
    {
        $this->pending(2, 2);
        $this->signInAs(1);
        $this->post(route('admin.review.bulk'), ['action' => 'reject', 'contractor_ids' => [2], 'confirmed' => '1', 'reason' => 'Insurance requires review'])
            ->assertRedirect(route('admin.review.index'))->assertSessionHasNoErrors();
        $contractor = User::findOrFail(2);
        $this->assertSame(0, $contractor->is_approved);
        $this->assertNull($contractor->approved_date);
        $this->assertTrue($contractor->isActiveAccount());
        $this->assertSame(1, $contractor->accountState->session_version);
        $this->assertTrue(DB::table('ActivityLogs')->where('user_id', 1)->where('action', 'like', '%Insurance requires review%')->exists());
    }

    public function test_stale_approved_or_missing_selection_cancels_the_entire_bulk_action_without_logs(): void
    {
        $this->pending(2, 2);
        $logs = DB::table('ActivityLogs')->count();
        $this->signInAs(1);

        foreach ([3, 9999] as $ineligibleId) {
            $this->postJson(route('admin.review.bulk'), ['action' => 'approve', 'contractor_ids' => [2, $ineligibleId], 'confirmed' => true])
                ->assertUnprocessable()->assertJsonValidationErrors('contractor_ids');
            $this->assertSame(2, User::findOrFail(2)->is_approved);
            $this->assertNull(User::findOrFail(2)->approved_date);
            $this->assertSame(0, AccountState::findOrFail(2)->session_version);
            $this->assertSame($logs, DB::table('ActivityLogs')->count());
        }
    }

    public function test_admin_blocked_and_archived_accounts_cannot_be_included_in_bulk_review(): void
    {
        $this->pending(2, 2);
        $this->pending(3, 0);
        $logs = DB::table('ActivityLogs')->count();
        $this->signInAs(1);
        foreach (['blocked_at', 'archived_at'] as $column) {
            AccountState::where('user_id', 3)->update(['blocked_at' => null, 'archived_at' => null, $column => now()]);
            $this->postJson(route('admin.review.bulk'), ['action' => 'archive', 'contractor_ids' => [2, 3], 'confirmed' => true])
                ->assertUnprocessable()->assertJsonValidationErrors('contractor_ids');
            $this->assertNull(AccountState::findOrFail(2)->archived_at);
        }
        $this->postJson(route('admin.review.bulk'), ['action' => 'reject', 'contractor_ids' => [1, 2], 'confirmed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('contractor_ids');
        $this->assertSame(2, User::findOrFail(2)->is_approved);
        $this->assertSame($logs, DB::table('ActivityLogs')->count());
    }

    public function test_bulk_requires_bounded_distinct_exact_ids_confirmation_and_a_known_action(): void
    {
        $this->pending(2, 2);
        $this->signInAs(1);
        $base = ['action' => 'approve', 'contractor_ids' => [2], 'confirmed' => true];
        foreach ([
            ['contractor_ids' => []], ['contractor_ids' => [2, '2']], ['contractor_ids' => ['garbage']],
            ['contractor_ids' => range(1, 51)], ['confirmed' => false], ['action' => 'delete'],
        ] as $invalid) {
            $this->postJson(route('admin.review.bulk'), array_replace($base, $invalid))->assertUnprocessable();
            $this->assertSame(2, User::findOrFail(2)->is_approved);
        }
    }

    public function test_review_print_and_bulk_are_admin_only_and_malformed_search_is_validated(): void
    {
        $this->get(route('admin.review.index'))->assertRedirect(route('login'));
        $this->signInAs(2);
        $this->get(route('admin.review.index'))->assertForbidden();
        $this->get(route('admin.contractors.print'))->assertForbidden();
        $this->post(route('admin.review.bulk'), ['action' => 'approve', 'contractor_ids' => [3], 'confirmed' => true])->assertForbidden();
        $this->signInAs(1);
        $this->getJson(route('admin.review.index', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('admin.review.index', ['approval' => 1]))->assertUnprocessable()->assertJsonValidationErrors('approval');
        $this->getJson(route('admin.contractors.index', ['service_ids' => ['not-an-id']]))->assertUnprocessable();
    }

    public function test_directory_and_print_share_all_service_w9_and_archive_filters_with_completed_job_counts(): void
    {
        DB::table('ContractorServices')->insert(['user_id' => 3, 'service_id' => 3]);
        DB::table('Files')->where('file_id', 2)->update(['file_type' => 'Other', 'file_name' => 'W9.pdf']);
        $document = (array) DB::table('Files')->where('file_id', 2)->first();
        DB::table('Files')->insert(array_replace($document, ['file_id' => 3, 'user_id' => 3, 'unique_file_id' => str_repeat('b', 32), 'file_type' => 'W9']));
        $this->signInAs(1);
        $filters = ['service_ids' => [3], 'missing_w9' => 1];

        foreach (['admin.contractors.index', 'admin.contractors.print'] as $route) {
            $this->get(route($route, $filters))->assertOk()
                ->assertViewHas('contractors', fn ($contractors) => $contractors->pluck('user_id')->all() === [2] && $contractors->first()->completed_jobs_count === 1);
        }
        AccountState::where('user_id', 2)->update(['archived_at' => now()]);
        foreach (['admin.contractors.index', 'admin.contractors.print'] as $route) {
            $this->get(route($route, $filters))->assertOk()->assertViewHas('contractors', fn ($contractors) => $contractors->isEmpty());
            $this->get(route($route, $filters + ['archived' => 1]))->assertOk()->assertViewHas('contractors', fn ($contractors) => $contractors->pluck('user_id')->all() === [2]);
        }
        AccountState::where('user_id', 2)->update(['blocked_at' => now()]);
        foreach (['admin.contractors.index', 'admin.contractors.print'] as $route) {
            $this->get(route($route, $filters + ['archived' => 1]))->assertOk()->assertViewHas('contractors', fn ($contractors) => $contractors->isEmpty());
        }
    }

    public function test_print_cap_reports_the_actual_total_without_exposing_registration_indicators(): void
    {
        $prototype = (array) DB::table('Users')->where('user_id', 2)->first();
        $rows = [];
        for ($id = 10; $id <= 1010; $id++) {
            $rows[] = array_replace($prototype, ['user_id' => $id, 'email' => 'print-'.$id.'@example.test', 'profile_photo' => null]);
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('Users')->insert($chunk);
        }
        AccountState::where('user_id', 2)->update(['registration_signals' => json_encode(['private-marker-review-only'])]);
        $this->signInAs(1);
        $this->get(route('admin.contractors.print'))->assertOk()
            ->assertViewHas('total', 1003)
            ->assertViewHas('contractors', fn ($contractors) => $contractors->count() === 1000)
            ->assertSee('first 1,000 matching contractors')
            ->assertDontSee('private-marker-review-only');
    }

    private function pending(int $id, int $state): void
    {
        DB::table('Users')->where('user_id', $id)->update(['is_approved' => $state, 'approved_date' => null]);
    }

    private function addContractor(int $id, array $overrides = []): void
    {
        $prototype = (array) DB::table('Users')->where('user_id', 2)->first();
        DB::table('Users')->insert(array_replace($prototype, ['user_id' => $id, 'email' => 'review-'.$id.'@example.test'], $overrides));
        (new AccountState)->forceFill(['user_id' => $id, 'email_verified_at' => now(), 'verified_email' => 'review-'.$id.'@example.test', 'session_version' => 0])->save();
    }
}
