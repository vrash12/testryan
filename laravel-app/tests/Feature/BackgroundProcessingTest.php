<?php

namespace Tests\Feature;

use App\Actions\Notifications\BusinessNotifications;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\IdentityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IdentityTestCase;

class BackgroundProcessingTest extends IdentityTestCase
{
    private function databaseQueue(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'm2_test', 'queue.failed.database' => 'm2_test']);
    }

    public function test_real_database_worker_delivers_identity_reset_and_business_intents(): void
    {
        $this->databaseQueue();
        config(['notifications.business_enabled' => true]);
        $user = $this->unverify(2);
        $user->sendEmailVerificationNotification();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        app(BusinessNotifications::class)->contractorApprovalChanged(User::findOrFail(1), User::findOrFail(3));
        $this->assertSame(3, NotificationDelivery::where('status', 'pending')->count());
        $this->assertSame(3, DB::table('app_queue_jobs')->count());
        Notification::assertNothingSent();
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();
        $this->assertSame(0, DB::table('app_queue_jobs')->count());
        $this->assertSame(3, NotificationDelivery::where('status', 'delivered')->count());
        $this->assertSame(0, DB::table('app_queue_failed_jobs')->count());
        $this->assertTrue(Schema::hasTable('Jobs'));
        $this->assertSame(2, DB::table('Jobs')->count());
    }

    public function test_scheduled_recovery_deduplicates_and_rechecks_expired_tokens(): void
    {
        $user = $this->unverify(2);
        // Record a failed intent using an external transport forbidden in testing.
        config(['mail.default' => 'smtp']);
        $user->sendEmailVerificationNotification();
        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('failed', $delivery->status);
        $this->databaseQueue();
        config(['mail.default' => 'array']);
        $this->travel(10)->minutes();
        $this->artisan('nestcrafters:retry-identity-mail', ['--queue' => true])->assertSuccessful();
        $this->artisan('nestcrafters:retry-identity-mail', ['--queue' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('app_queue_jobs')->count());
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();
        $this->assertSame('delivered', $delivery->fresh()->status);
        Notification::assertSentToTimes($user, IdentityNotification::class, 1);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $this->travel(61)->minutes();
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();
        $this->assertSame('skipped', NotificationDelivery::where('id', '!=', $delivery->id)->firstOrFail()->status);
        $this->travelBack();
    }
}
