<?php

namespace Tests\Feature;

use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_every_protected_shell(): void
    {
        foreach (['/dashboard', '/admin', '/contractor'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }

        $this->get('/login')->assertOk()->assertSee('Forgot your password?');
    }

    public function test_admin_and_contractor_shells_enforce_roles_and_redirects(): void
    {
        foreach (['admin' => 1, 'contractor' => 0] as $role => $flag) {
            $user = new User;
            $user->forceFill(['user_id' => 10, 'is_admin' => $flag, 'is_approved' => 1]);
            $this->prepareIdentity($user);
            $this->actingAs($user);
            $this->get('/dashboard')->assertRedirect(route($role.'.dashboard'));
            $this->get('/'.$role)->assertOk()->assertSee(ucfirst($role));
            $otherRole = $role === 'admin' ? 'contractor' : 'admin';
            $this->get('/'.$otherRole)->assertForbidden()->assertSee('Access is restricted');
        }
    }

    public function test_unknown_role_is_denied_instead_of_defaulting_to_contractor(): void
    {
        $user = new User;
        $user->forceFill(['user_id' => 11, 'is_admin' => null]);
        $this->prepareIdentity($user);
        $this->actingAs($user);
        $this->get('/dashboard')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/contractor')->assertForbidden();
    }

    public function test_unapproved_contractors_cannot_open_the_protected_shell(): void
    {
        foreach ([0, 2, null] as $approval) {
            $user = new User;
            $user->forceFill(['user_id' => 13, 'is_admin' => 0, 'is_approved' => $approval]);
            $this->prepareIdentity($user);
            $this->actingAs($user)->get('/contractor')->assertForbidden();
        }
    }

    public function test_local_previews_require_environment_and_flag_and_never_sign_in(): void
    {
        config(['nestcrafters.shell_previews' => true]);
        foreach (['testing', 'production'] as $environment) {
            $this->app->instance('env', $environment);
            $this->get('/local/preview/admin')->assertNotFound();
        }

        $this->app->instance('env', 'local');
        config(['nestcrafters.shell_previews' => false]);
        $this->get('/local/preview/admin')->assertNotFound();
        config(['nestcrafters.shell_previews' => true]);
        foreach (['admin', 'contractor'] as $role) {
            $this->get('/local/preview/'.$role)->assertOk()->assertSee('Local shell preview.');
            $this->assertGuest();
        }
        $this->get('/local/preview/owner')->assertNotFound();
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_authentication(): void
    {
        $user = new User;
        $user->forceFill(['user_id' => 12, 'is_admin' => 1]);
        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_public_health_discloses_no_infrastructure_and_does_not_query_database(): void
    {
        $this->app->instance('env', 'production');
        DB::shouldReceive('selectOne')->never();
        $this->get('/health')->assertOk()->assertDontSee('Local database')->assertDontSee('DB_PASSWORD');
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_local_health_reports_database_failure_without_disclosing_details(): void
    {
        $this->app->instance('env', 'local');
        DB::shouldReceive('selectOne')->andThrow(new \RuntimeException('private-db-secret'));
        $this->get('/health')->assertStatus(503)->assertSee('Unavailable')->assertDontSee('private-db-secret');
        $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_production_error_page_hides_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-error', fn () => throw new \RuntimeException('private-exception-secret'));
        $this->get('/test-error')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('private-exception-secret');
    }

    public function test_private_storage_has_no_public_serving_routes_or_links(): void
    {
        $this->assertSame('private', config('filesystems.default'));
        $this->assertFalse(config('filesystems.disks.private.serve'));
        $this->assertSame([], config('filesystems.links'));
        $this->get('/storage/private.txt')->assertNotFound();
    }

    public function test_shell_urls_and_session_cookie_support_a_subdirectory(): void
    {
        $this->app->instance('env', 'local');
        config(['nestcrafters.shell_previews' => true, 'session.path' => '/contractors']);
        // Symfony derives the request base path from front-controller server variables.
        $response = $this->call('GET', '/contractors/local/preview/admin', [], [], [], [
            'SCRIPT_NAME' => '/contractors/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/contractors/index.php/local/preview/admin',
        ]);
        $response->assertOk()->assertSee('/contractors/local/preview/admin');
        $response->assertCookie('nestcrafters_session');
        $this->assertSame('/contractors', $response->headers->getCookies()[0]->getPath());
    }

    private function prepareIdentity(User $user): void
    {
        $user->email = 'foundation@example.test';
        $user->setRelation('accountState', (new AccountState)->forceFill(['email_verified_at' => now(), 'verified_email' => $user->email, 'session_version' => 0]));
        $this->withSession(['auth_session_version' => 0]);
    }
}
