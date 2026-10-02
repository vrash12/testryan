<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReleasePreflight extends Command
{
    protected $signature = 'release:preflight {--strict : Treat local-only transports and non-production settings as release failures} {--json : Emit machine-readable results}';

    protected $description = 'Run a read-only deployment readiness preflight';

    /** @var array<string, array{ok: bool, detail: string}> */
    private array $checks = [];

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $url = (string) config('app.url');
        $parsed = parse_url($url);
        $this->check('app_key', filled(config('app.key')) && config('app.key') !== 'base64:', 'APP_KEY is configured without printing its value.');
        $this->check('app_debug', ! config('app.debug'), 'APP_DEBUG=false.');
        $this->check('app_url', $parsed !== false && isset($parsed['scheme'], $parsed['host']), 'APP_URL has a valid scheme and host.');
        $this->check('https', ! $strict || ($parsed['scheme'] ?? null) === 'https', $strict ? 'APP_URL uses HTTPS.' : 'HTTPS check deferred outside strict release mode.');
        $this->check('base_path', ! $strict || str_ends_with(rtrim((string) ($parsed['path'] ?? ''), '/'), '/contractors'), $strict ? 'APP_URL includes the approved /contractors mount.' : 'Base path is recorded for hosting review.');
        $this->check('private_disk', config('filesystems.default') === 'private' && config('filesystems.disks.private.serve') === false, 'The default disk is private and not directly served.');
        $this->check('private_root', is_dir(config('filesystems.disks.private.root')) && is_writable(config('filesystems.disks.private.root')), 'Private storage exists and is writable.');
        $this->check('session_cookie', filled(config('session.cookie')) && (! $strict || config('session.secure')), 'The session cookie is named; strict mode requires Secure cookies.');
        $this->check('queue', ! $strict || config('queue.default') === 'database', $strict ? 'The database queue is configured for this hosting setup.' : 'Queue is allowed to remain sync for local verification.');
        $this->check('mail', ! $strict || ! in_array(config('mail.default'), ['log', 'array'], true), $strict ? 'An external mail transport is configured.' : 'Local mail sink is allowed outside strict release mode.');
        $this->check('brevo', ! $strict || (config('mail.default') === 'brevo' && filled(config('services.brevo.key')) && ! app()->environment(['local', 'testing'])), 'Strict mode requires Brevo, its API key, and a hosting environment; credentials are not printed or tested.');
        $this->check('background', ! $strict || config('background.enabled'), $strict ? 'Background schedule enabled; actual host cron execution must be verified separately.' : 'Background activation check deferred outside strict mode.');
        $this->check('insurance_schedule', ! $strict || (config('background.insurance_enabled') && config('notifications.business_enabled') && config('notifications.events.document.expiring')), $strict ? 'Insurance schedule and business/expiry mail switches enabled; confirm business timezone and recipients.' : 'Insurance schedule activation check deferred outside strict mode.');
        $this->check('registration_bot', ! $strict || ! config('identity.registration_enabled') || config('identity.bot_driver') === 'turnstile', $strict ? 'Public registration uses the configured bot-control provider.' : 'Bot-control release check deferred outside strict mode.');

        try {
            DB::select('SELECT 1');
            $this->check('database', true, 'Database connection accepted SELECT 1.');
            $this->check('migrations', Schema::hasTable('app_migrations'), 'Application migration table exists.');
            $this->check('queue_tables', ! $strict || (Schema::hasTable('app_queue_jobs') && Schema::hasTable('app_queue_failed_jobs')), 'Strict mode requires prefixed queue and failed-job tables.');
        } catch (Throwable $exception) {
            $this->check('database', false, 'Database connection failed; details remain in the private log.');
            $this->check('migrations', false, 'Migration table could not be checked.');
        }

        $manifest = public_path('build/manifest.json');
        $this->check('assets', is_file($manifest) && is_readable($manifest), 'Compiled Vite manifest is present.');

        if ($this->option('json')) {
            $this->line(json_encode(['strict' => $strict, 'checks' => $this->checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result', 'Detail'], collect($this->checks)->map(fn (array $check, string $name): array => [$name, $check['ok'] ? 'PASS' : 'BLOCKED', $check['detail']])->values()->all());
            $this->newLine();
            $this->line($strict ? 'Strict release preflight: '.($this->passed() ? 'PASS' : 'BLOCKED') : 'Local preflight: '.($this->passed() ? 'PASS' : 'BLOCKED').'. Run --strict before a rollout.');
        }

        return $this->passed() ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $name, bool $ok, string $detail): void
    {
        $this->checks[$name] = ['ok' => $ok, 'detail' => $detail];
    }

    private function passed(): bool
    {
        return collect($this->checks)->every(fn (array $check): bool => $check['ok']);
    }
}
