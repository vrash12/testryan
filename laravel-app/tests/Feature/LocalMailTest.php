<?php

namespace Tests\Feature;

use App\Mail\LocalMailCheck;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LocalMailTest extends TestCase
{
    public function test_local_mail_command_captures_a_synthetic_message(): void
    {
        $this->app->instance('env', 'local');
        config(['mail.default' => 'log']);
        Mail::fake();
        $this->artisan('nestcrafters:mail-test')->assertSuccessful();
        Mail::assertSent(LocalMailCheck::class, fn ($mail) => $mail->hasTo('preview@nestcrafters.test'));
    }

    public function test_mail_command_refuses_nonlocal_environment_or_external_transport(): void
    {
        Mail::fake();
        $this->app->instance('env', 'production');
        config(['mail.default' => 'log']);
        $this->artisan('nestcrafters:mail-test')->assertFailed();
        $this->app->instance('env', 'local');
        config(['mail.default' => 'smtp']);
        $this->artisan('nestcrafters:mail-test')->assertFailed();
        Mail::assertNothingSent();
    }
}
