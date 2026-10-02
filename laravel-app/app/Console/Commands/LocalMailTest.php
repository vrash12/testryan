<?php

namespace App\Console\Commands;

use App\Mail\LocalMailCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class LocalMailTest extends Command
{
    protected $signature = 'nestcrafters:mail-test';

    protected $description = 'Capture a synthetic mail message using the local log transport';

    public function handle(): int
    {
        if (! app()->environment('local') || config('mail.default') !== 'log') {
            $this->error('This check requires APP_ENV=local and MAIL_MAILER=log.');

            return self::FAILURE;
        }

        Mail::to('preview@nestcrafters.test')->send(new LocalMailCheck);
        $this->info('Test message captured in storage/logs/mail.log. No external delivery.');

        return self::SUCCESS;
    }
}
