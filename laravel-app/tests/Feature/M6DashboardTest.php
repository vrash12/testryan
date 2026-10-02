<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class M6DashboardTest extends IdentityTestCase
{
    public function test_admin_dashboard_exposes_real_approval_job_and_document_metrics(): void
    {
        $this->signInAs(1);

        DB::table('Users')->where('user_id', 2)->update(['is_approved' => 0]);
        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 2]);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin dashboard')
            ->assertSee('New pending')
            ->assertSee('Review pending')
            ->assertSee('Missing W9')
            ->assertSee('Expired insurance')
            ->assertViewHas('metrics', fn ($metrics) => $metrics['jobs'] === 1 && $metrics['pending_zero'] === 1 && $metrics['pending_review'] === 1);

        // The fixture has one completed job and one open pending job. The
        // dashboard's open count must not include the completed row.
    }

    public function test_admin_due_and_insurance_views_use_date_only_inclusive_windows(): void
    {
        $today = today();
        DB::table('Jobs')->insert([
            ['job_id' => 7, 'entered_datetime' => now(), 'due_date' => $today->copy()->subDay()->toDateString(), 'job_name' => 'M6 overdue', 'job_description' => 'Overdue', 'is_accepted' => 0, 'accepted_datetime' => null, 'terms' => null, 'contractor_id' => null, 'is_completed' => 0, 'completed_datetime' => null, 'approved_price' => null, 'deposit_amount' => null, 'who_supplies_materials' => null],
            ['job_id' => 8, 'entered_datetime' => now(), 'due_date' => $today->copy()->addDays(7)->toDateString(), 'job_name' => 'M6 due soon', 'job_description' => 'Upcoming', 'is_accepted' => 0, 'accepted_datetime' => null, 'terms' => null, 'contractor_id' => null, 'is_completed' => 0, 'completed_datetime' => null, 'approved_price' => null, 'deposit_amount' => null, 'who_supplies_materials' => null],
        ]);
        DB::table('Files')->insert([
            ['file_id' => 7, 'user_id' => 2, 'unique_file_id' => str_repeat('b', 32), 'file_type' => 'Insurance', 'file_name' => 'inclusive.pdf', 'file_data' => '%PDF-1.4', 'mime_type' => 'application/pdf', 'upload_date' => now(), 'expiry_date' => $today->copy()->addDays(30)->toDateString(), 'archived' => 0],
            ['file_id' => 8, 'user_id' => 3, 'unique_file_id' => str_repeat('c', 32), 'file_type' => 'Insurance', 'file_name' => 'expired.pdf', 'file_data' => '%PDF-1.4', 'mime_type' => 'application/pdf', 'upload_date' => now(), 'expiry_date' => $today->copy()->subDay()->toDateString(), 'archived' => 0],
        ]);

        $this->signInAs(1);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('M6 overdue')->assertSee('M6 due soon')->assertSee('inclusive.pdf')->assertSee('expired.pdf');
    }

    public function test_contractor_dashboard_is_scoped_and_surfaces_next_action(): void
    {
        DB::table('Jobs')->insert([
            ['job_id' => 7, 'entered_datetime' => now(), 'due_date' => today()->addDay()->toDateString(), 'job_name' => 'Contractor two job', 'job_description' => 'Own', 'is_accepted' => 0, 'accepted_datetime' => null, 'terms' => null, 'contractor_id' => 2, 'is_completed' => 0, 'completed_datetime' => null, 'approved_price' => null, 'deposit_amount' => null, 'who_supplies_materials' => null],
            ['job_id' => 8, 'entered_datetime' => now(), 'due_date' => today()->addDay()->toDateString(), 'job_name' => 'Contractor three job', 'job_description' => 'Other', 'is_accepted' => 0, 'accepted_datetime' => null, 'terms' => null, 'contractor_id' => 3, 'is_completed' => 0, 'completed_datetime' => null, 'approved_price' => null, 'deposit_amount' => null, 'who_supplies_materials' => null],
        ]);

        $this->signInAs(2);
        $this->get(route('contractor.dashboard'))->assertOk()->assertSee('Contractor two job')->assertDontSee('Contractor three job')->assertSee('Upload your W9.');
    }

    public function test_dashboard_counts_are_not_capped_by_visible_cards_and_due_filters_stay_owned(): void
    {
        $base = (array) DB::table('Jobs')->where('job_id', 5)->first();
        foreach (range(10, 24) as $id) {
            DB::table('Jobs')->insert(array_replace($base, ['job_id' => $id, 'contractor_id' => 2, 'job_name' => 'Own overdue '.$id, 'due_date' => today()->subDay()->toDateString(), 'accepted_datetime' => null, 'completed_datetime' => null]));
        }
        $user = (array) DB::table('Users')->where('user_id', 3)->first();
        foreach (range(10, 21) as $id) {
            DB::table('Users')->insert(array_replace($user, ['user_id' => $id, 'email' => 'counts'.$id.'@example.test']));
        }
        $this->signInAs(1);
        $this->get(route('admin.dashboard'))
            ->assertViewHas('metrics', fn ($metrics) => $metrics['jobs'] === 16 && $metrics['w9_missing'] === 14)
            ->assertViewHas('missingW9', fn ($rows) => $rows->count() === 8);
        $this->signInAs(2);
        $this->get(route('contractor.dashboard'))
            ->assertViewHas('metrics', fn ($metrics) => $metrics['jobs'] === 15 && $metrics['pending_jobs'] === 15 && $metrics['completed_jobs'] === 1)
            ->assertViewHas('jobs', fn ($rows) => $rows->count() === 10);
        $this->get(route('contractor.jobs.index', ['due' => 'overdue']))
            ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 15 && $jobs->every(fn ($job) => $job->contractor_id === 2 && $job->is_completed === 0));
        $this->getJson(route('contractor.jobs.index', ['q' => ['malformed']]))->assertUnprocessable();
    }

    public function test_global_document_filters_are_admin_only_inclusive_and_exclude_inactive_owners(): void
    {
        $base = (array) DB::table('Files')->where('file_id', 2)->first();
        foreach ([7 => today()->toDateString(), 8 => today()->addDays(30)->toDateString(), 9 => today()->addDays(31)->toDateString(), 10 => today()->subDay()->toDateString()] as $id => $expiry) {
            DB::table('Files')->insert(array_replace($base, ['file_id' => $id, 'unique_file_id' => md5((string) $id), 'file_name' => 'Expiry '.$id.'.pdf', 'expiry_date' => $expiry]));
        }
        DB::table('Files')->insert(array_replace($base, ['file_id' => 11, 'unique_file_id' => md5('11'), 'user_id' => 3, 'expiry_date' => today()->toDateString()]));
        DB::table('app_account_states')->where('user_id', 3)->update(['archived_at' => now()]);
        $this->signInAs(1);
        $this->get(route('admin.documents.index', ['status' => 'expiring']))->assertOk()
            ->assertSee('Expiry 7.pdf')->assertSee('Expiry 8.pdf')->assertDontSee('Expiry 9.pdf')->assertDontSee('Expiry 10.pdf')
            ->assertViewHas('documents', fn ($documents) => $documents->total() === 2);
        $this->get(route('admin.documents.index', ['status' => 'expired']))->assertOk()->assertSee('Expiry 10.pdf')->assertDontSee('Expiry 7.pdf');
        $this->getJson(route('admin.documents.index', ['q' => ['malformed']]))->assertUnprocessable();
        $this->signInAs(2);
        $this->get(route('admin.documents.index'))->assertForbidden();
    }
}
