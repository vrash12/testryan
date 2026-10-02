<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BrevoCheck extends Command
{
    protected $signature = 'nestcrafters:brevo-check {--send-to= : Send one synthetic message to an explicitly chosen test inbox}';

    protected $description = 'Check Brevo settings without printing secrets; optionally send one test message';

    public function handle(): int
    {
        $checks = [
            'Hosting environment (not local/testing)' => ! app()->environment(['local', 'testing']),
            'Brevo selected' => config('mail.default') === 'brevo',
            'API key configured' => filled(config('services.brevo.key')),
            'Sender address configured' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
                && ! preg_match('/\.(test|invalid|localhost)$/i', (string) config('mail.from.address')),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
        ];
        $this->table(['Check', 'Result'], collect($checks)->map(fn ($ok, $name) => [$name, $ok ? 'PASS' : 'MISSING'])->values()->all());
        if (in_array(false, $checks, true)) {
            return self::FAILURE;
        }
        if (! $this->option('send-to')) {
            $this->info('Configuration only. DNS authentication, API credentials and mailbox delivery have not been tested.');

            return self::SUCCESS;
        }
        $recipient = (string) $this->option('send-to');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient)) {
            $this->error('Supply one valid test inbox.');

            return self::FAILURE;
        }
        try {
            Mail::raw('NestCrafters synthetic delivery check. No contractor data is included. Confirm receipt in the intended test inbox.', function ($message) use ($recipient): void {
                $message->to($recipient)->subject('NestCrafters email delivery check');
            });
        } catch (Throwable) {
            $this->error('Brevo did not confirm acceptance. Check the provider account and configuration; no response body or secret is printed.');

            return self::FAILURE;
        }
        $this->info('Brevo accepted the test message. Confirm receipt/spam-folder placement in the test inbox; API acceptance is not mailbox delivery.');

        return self::SUCCESS;
    }
}
