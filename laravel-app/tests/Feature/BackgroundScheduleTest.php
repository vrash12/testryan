<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class BackgroundScheduleTest extends TestCase
{
    private function scheduled(): array
    {
        \Illuminate\Support\Facades\Schedule::swap(new Schedule);
        require base_path('routes/console.php');

        return \Illuminate\Support\Facades\Schedule::events();
    }

    public function test_schedule_is_opt_in_and_requires_durable_queue(): void
    {
        config(['background.enabled' => false]);
        $this->assertCount(0, $this->scheduled());
        config(['background.enabled' => true, 'queue.default' => 'sync']);
        $this->assertCount(0, $this->scheduled());
    }

    public function test_shared_host_schedule_runs_bounded_worker_after_reminders_and_retries(): void
    {
        config(['background.enabled' => true, 'background.shared_host_worker' => true, 'background.insurance_enabled' => true, 'background.insurance_time' => '08:15', 'queue.default' => 'database', 'app.timezone' => 'America/New_York']);
        $events = $this->scheduled();
        $this->assertCount(3, $events);
        $this->assertStringContainsString('notify-document-expiry --staged', $events[0]->command);
        $this->assertSame('15 8 * * *', $events[0]->expression);
        $this->assertSame('America/New_York', $events[0]->timezone);
        $this->assertStringContainsString('retry-identity-mail --queue', $events[1]->command);
        $this->assertStringContainsString('--stop-when-empty --max-time=45', $events[2]->command);
        $this->assertSame('* * * * *', $events[2]->expression);
        foreach ($events as $event) {
            $this->assertTrue($event->withoutOverlapping);
        }
        config(['background.shared_host_worker' => false, 'background.insurance_enabled' => false]);
        $this->assertCount(1, $this->scheduled());
    }
}
