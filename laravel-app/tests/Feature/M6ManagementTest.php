<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class M6ManagementTest extends IdentityTestCase
{
    public function test_admin_service_search_is_paginated_and_contractors_cannot_manage_catalog(): void
    {
        $this->signInAs(1);
        DB::table('Services')->insert(array_map(fn (int $id): array => ['service_id' => $id, 'service_name' => 'Searchable '.$id], range(10, 45)));
        $this->get(route('admin.services.index', ['q' => 'Searchable 3']))->assertOk()->assertSee('Searchable 30')->assertDontSee('Searchable 10');
        $this->getJson(route('admin.services.index', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');

        $this->signInAs(2);
        $this->get(route('admin.services.index'))->assertForbidden();
    }

    public function test_templates_are_admin_only_unique_transactional_and_preview_content_is_sanitized(): void
    {
        $this->signInAs(1);
        $response = $this->post(route('admin.templates.store'), ['name' => 'Job Insert', 'type' => 'Jobs', 'html_content' => '<p><strong>Safe</strong><script>alert(1)</script></p>']);
        $response->assertRedirect(route('admin.templates.index'));
        $template = Template::where('name', 'Job Insert')->firstOrFail();
        $response = $this->get(route('admin.templates.content', $template))->assertOk()->assertJsonPath('html_content', '<p><strong>Safe</strong></p>');
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->post(route('admin.templates.store'), ['name' => 'Job Insert', 'type' => 'Jobs', 'html_content' => '<p>duplicate</p>'])->assertSessionHasErrors('name');
        $this->put(route('admin.templates.update', $template), ['name' => 'Job Insert Updated', 'type' => 'Email', 'html_content' => '<p>Welcome</p>'])->assertRedirect();
        $this->delete(route('admin.templates.destroy', $template->fresh()))->assertRedirect(route('admin.templates.index'));
        $this->assertDatabaseHas('ActivityLogs', ['user_id' => 1, 'action' => 'Security: created template "Job Insert".']);
        $this->assertDatabaseHas('ActivityLogs', ['user_id' => 1, 'action' => 'Security: deleted template "Job Insert Updated".']);

        $this->signInAs(2);
        $this->get(route('admin.templates.index'))->assertForbidden();
    }

    public function test_activity_logs_filter_orphan_actors_and_print_without_mutation(): void
    {
        $this->signInAs(1);
        $this->get(route('admin.activity.index', ['action' => 'Deleted', 'date_from' => '2025-01-01', 'date_to' => '2025-12-31']))->assertOk()->assertSee('Deleted fixture user association');
        $this->get(route('admin.activity.index', ['user_id' => 11]))->assertOk()->assertSee('Deleted user #11');
        $this->get(route('admin.activity.print', ['action' => 'Fixture']))->assertOk()->assertSee('Activity logs')->assertSee('Fixture');
        $this->signInAs(2);
        $this->get(route('admin.activity.index'))->assertForbidden();
    }

    public function test_contractors_cannot_read_template_content_or_write_template_records(): void
    {
        $template = Template::findOrFail(2);
        $before = $template->getAttributes();
        $this->signInAs(2);
        $this->getJson(route('admin.templates.content', $template))->assertForbidden();
        $this->post(route('admin.templates.store'), ['name' => 'Unauthorized template', 'type' => 'Jobs', 'html_content' => '<p>Denied</p>'])->assertForbidden();
        $this->put(route('admin.templates.update', $template), ['name' => 'Changed without permission', 'type' => 'Jobs', 'html_content' => '<p>Denied</p>'])->assertForbidden();
        $this->delete(route('admin.templates.destroy', $template))->assertForbidden();
        $this->assertSame($before, $template->fresh()->getAttributes());
        $this->assertDatabaseMissing('Templates', ['name' => 'Unauthorized template']);
        $this->get(route('admin.activity.print'))->assertForbidden();
    }

    public function test_activity_filters_reject_array_values_invalid_dates_and_reversed_ranges(): void
    {
        $this->signInAs(1);
        $this->getJson(route('admin.activity.index', ['action' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->getJson(route('admin.activity.index', ['user_id' => ['1']]))->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->getJson(route('admin.activity.index', ['date_from' => '2025-02-30']))->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $this->getJson(route('admin.activity.print', ['date_to' => '2025-01-02garbage']))->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson(route('admin.activity.index', ['date_from' => '2025-01-03', 'date_to' => '2025-01-02']))->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->get(route('admin.activity.index', ['date_from' => '2025-01-02', 'date_to' => '2025-01-02']))->assertOk()->assertSee('Deleted fixture user association');
    }

    public function test_activity_print_shows_matching_total_and_explicit_cap_without_changing_logs(): void
    {
        $this->signInAs(1);
        $records = array_map(fn (int $id): array => [
            'log_id' => $id,
            'user_id' => 1,
            'action' => 'Print cap fixture '.$id,
            'timestamp' => '2025-01-02 12:00:00',
        ], range(1000, 3000));
        DB::table('ActivityLogs')->insert($records);
        $before = DB::table('ActivityLogs')->count();

        $this->get(route('admin.activity.print', ['action' => 'Print cap fixture']))
            ->assertOk()
            ->assertViewHas('total', 2001)
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 2000)
            ->assertSee('2,000 of 2,001 matching records')
            ->assertSee('This print view includes the latest 2,000 matching records.')
            ->assertDontSee('Print cap fixture 1000')
            ->assertSee('Print cap fixture 3000');
        $this->assertSame($before, DB::table('ActivityLogs')->count());
    }

    public function test_activity_stats_use_filtered_account_associations_and_business_date(): void
    {
        $this->signInAs(1);
        DB::table('ActivityLogs')->insert([
            ['log_id' => 100, 'user_id' => 1, 'action' => 'Stats fixture admin', 'timestamp' => today()->format('Y-m-d').' 12:00:00'],
            ['log_id' => 101, 'user_id' => 2, 'action' => 'Stats fixture contractor', 'timestamp' => today()->format('Y-m-d').' 12:00:00'],
            ['log_id' => 102, 'user_id' => 4000, 'action' => 'Stats fixture missing account', 'timestamp' => today()->format('Y-m-d').' 12:00:00'],
            ['log_id' => 103, 'user_id' => 1, 'action' => 'Stats fixture older', 'timestamp' => today()->subDay()->format('Y-m-d').' 12:00:00'],
            ['log_id' => 104, 'user_id' => 1, 'action' => 'Unrelated activity', 'timestamp' => today()->format('Y-m-d').' 12:00:00'],
        ]);
        $this->get(route('admin.activity.index', ['action' => 'Stats fixture']))
            ->assertOk()
            ->assertViewHas('stats', ['total' => 4, 'admin_associated' => 2, 'contractor_associated' => 1, 'orphan' => 1, 'today' => 3])
            ->assertSee('Associated account')
            ->assertDontSee('Unrelated activity');
        $this->get(route('admin.activity.print', ['action' => 'Stats fixture', 'user_id' => 2]))
            ->assertOk()
            ->assertViewHas('stats', ['total' => 1, 'admin_associated' => 0, 'contractor_associated' => 1, 'orphan' => 0, 'today' => 1]);
    }
}
