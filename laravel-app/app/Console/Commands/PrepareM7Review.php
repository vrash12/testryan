<?php

namespace App\Console\Commands;

use App\Actions\Import\LocalImportGuard;
use App\Models\InboxNotification;
use App\Models\NotificationEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Build an isolated, deterministic M7 visual-review dataset.
 *
 * This command deliberately resets only m2_test. It is a local QA fixture,
 * not an import and never reads or writes the production connection.
 */
class PrepareM7Review extends Command
{
    protected $signature = 'm7:prepare-review {--no-migrate : Seed an already-migrated disposable target}';

    protected $description = 'Prepare the guarded local M7 visual-review fixture in m2_test';

    private const TARGET = 'm2_test';

    private const SOURCE = 'm2_source_test';

    public function handle(LocalImportGuard $guard): int
    {
        if (! config('database.connections.'.self::TARGET.'.username')) {
            $this->components->error('M2_TEST_USERNAME is not configured; no review database was touched.');

            return self::FAILURE;
        }

        // This check is intentionally before migrate:fresh. It protects both
        // the disposable target and the local source identity on every run.
        $source = DB::connection(self::SOURCE);
        $target = DB::connection(self::TARGET);
        $guard->check($source, $target);

        config(['database.default' => self::TARGET, 'legacy.target_connection' => self::TARGET, 'legacy.source_connection' => self::SOURCE]);
        DB::purge(self::TARGET);

        if (! $this->option('no-migrate')) {
            $this->callSilently('migrate:fresh', ['--database' => self::TARGET, '--force' => true]);
        }

        $this->assertSchema();
        DB::connection(self::TARGET)->transaction(fn (): null => $this->seed());

        $this->components->info('M7 review fixture ready in nestcrafters_m2_test.');
        $this->line('Admin:      admin.review@example.test / fixture-known-password');
        $this->line('Approved:   approved.contractor@example.test / fixture-known-password');
        $this->line('Pending:    pending.contractor@example.test / fixture-known-password');
        $this->line('Unverified: unverified.contractor@example.test / fixture-known-password');
        $this->line('Review files are private; use the authenticated download/preview routes.');

        return self::SUCCESS;
    }

    private function assertSchema(): void
    {
        $schema = DB::connection(self::TARGET)->getSchemaBuilder();
        foreach (['Users', 'Jobs', 'Services', 'Files', 'Templates', 'app_account_states', 'app_private_files', 'app_notification_events', 'app_notification_inbox'] as $table) {
            if (! $schema->hasTable($table)) {
                throw new RuntimeException('The disposable target is not migrated: missing '.$table.'.');
            }
        }
    }

    private function seed(): null
    {
        $db = DB::connection(self::TARGET);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZioAAAAASUVORK5CYII=', true);
        $pdf = $this->reviewPdf();
        $now = now()->setTimezone('UTC');
        $day = $now->toDateString();
        $long = 'Review fixture — an intentionally long contractor and job title that exercises wrapping at desktop and narrow mobile widths without leaking private metadata.';

        $users = [
            $this->user(1001, 'admin.review@example.test', 'Review', 'Administrator', 'M7 Review Operations', 1, 1, $now->copy()->subMonths(9)->toDateTimeString()),
            $this->user(1002, 'approved.contractor@example.test', 'Approved', 'Contractor', $long, 0, 1, $now->copy()->subMonths(6)->toDateTimeString()),
            $this->user(1003, 'pending.contractor@example.test', 'Pending', 'Contractor', 'Pending approval company', 0, 2, $now->copy()->subDays(3)->toDateTimeString()),
            $this->user(1004, 'unverified.contractor@example.test', 'Unverified', 'Contractor', 'Email verification company', 0, 1, $now->copy()->subDays(4)->toDateTimeString()),
        ];
        $users[1]['profile_photo'] = $png;
        $db->table('Users')->insert($users);
        $db->table('app_account_states')->insert(array_map(fn (array $user): array => [
            'user_id' => $user['user_id'],
            'email_verified_at' => $now,
            'verified_email' => $user['email'],
            'session_version' => 0,
            'blocked_at' => null,
            'archived_at' => null,
            'registration_signals' => $user['user_id'] === 1003 ? json_encode(['review_fixture' => true, 'duplicate_domain' => 'example.test']) : null,
            'registration_ip_hash' => null,
        ], $users));
        foreach ($users as $user) {
            $db->table('app_account_states')->where('user_id', $user['user_id'])->update(['email_verified_at' => $now, 'verified_email' => $user['email']]);
        }
        $db->table('app_account_states')->where('user_id', 1004)->update(['email_verified_at' => null, 'verified_email' => null]);

        $db->table('Services')->insert([
            ['service_id' => 201, 'service_name' => 'Kitchen remodeling'],
            ['service_id' => 202, 'service_name' => 'Bathroom renovation'],
            ['service_id' => 203, 'service_name' => 'Fixture installation'],
        ]);
        $db->table('ContractorServices')->insert([
            ['user_id' => 1002, 'service_id' => 201], ['user_id' => 1002, 'service_id' => 202],
            ['user_id' => 1003, 'service_id' => 201], ['user_id' => 1004, 'service_id' => 203],
        ]);

        $jobs = [
            $this->job(2001, 'Pending review job with a long title for responsive layout checks', $now->copy()->addDays(2)->toDateString(), 1002, 0, 0, $long),
            $this->job(2002, 'Accepted contractor job', $now->copy()->addDays(5)->toDateString(), 1002, 1, 0, 'Accepted job description with terms and materials responsibility.'),
            $this->job(2003, 'Completed historical job', $now->copy()->subDays(4)->toDateString(), 1002, 1, 1, 'Completed fixture job.'),
            $this->job(2004, 'Overdue pending job', $now->copy()->subDays(2)->toDateString(), 1002, 0, 0, 'Overdue job used for dashboard warning state.'),
            $this->job(2005, 'Pending contractor assignment review', $now->copy()->addDays(10)->toDateString(), 1003, 0, 0, 'Pending contractor account must not gain active work access.'),
            $this->job(2006, 'Unassigned job', $now->copy()->addDays(14)->toDateString(), null, 0, 0, 'Unassigned admin queue fixture.'),
        ];
        $db->table('Jobs')->insert($jobs);
        $db->table('Job_Services')->insert([
            ['job_service_id' => 20001, 'job_id' => 2001, 'service_id' => 201], ['job_service_id' => 20002, 'job_id' => 2001, 'service_id' => 202],
            ['job_service_id' => 20003, 'job_id' => 2002, 'service_id' => 202], ['job_service_id' => 20004, 'job_id' => 2003, 'service_id' => 201],
            ['job_service_id' => 20005, 'job_id' => 2004, 'service_id' => 203], ['job_service_id' => 20006, 'job_id' => 2005, 'service_id' => 201],
        ]);
        $db->table('Job_Notes')->insert([
            ['note_id' => 20001, 'job_id' => 2001, 'note_text' => 'Review note: confirm permit and material responsibility before acceptance.', 'created_datetime' => $now->toDateTimeString(), 'created_by' => 1001],
            ['note_id' => 20002, 'job_id' => 2002, 'note_text' => 'Contractor progress note for the approved job.', 'created_datetime' => $now->copy()->subDay()->toDateTimeString(), 'created_by' => 1002],
        ]);
        $db->table('Job_Media')->insert([
            ['media_id' => 20001, 'job_id' => 2002, 'file_name' => 'accepted-job-progress.png', 'file_data' => $png, 'mime_type' => 'image/png', 'tag' => 'During', 'upload_datetime' => $now->toDateTimeString(), 'uploaded_by' => 1002],
        ]);

        $db->table('Templates')->insert([
            ['template_id' => 2001, 'name' => 'M7 Job Welcome', 'type' => 'Jobs', 'html_content' => '<h2>Welcome</h2><p>Use this template to review the job description.</p>', 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString()],
            ['template_id' => 2002, 'name' => 'M7 Contractor Follow-up', 'type' => 'Contractor', 'html_content' => '<p>Please confirm your services and upload current insurance.</p>', 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString()],
        ]);

        $files = [
            $this->file(21001, 1002, 'W9', 'approved-w9.pdf', $pdf, null, $now),
            $this->file(21002, 1002, 'Insurance', 'insurance-expiring-in-7-days.pdf', $pdf, $now->copy()->addDays(7)->toDateString(), $now),
            $this->file(21003, 1002, 'Insurance', 'insurance-expired.pdf', $pdf, $now->copy()->subDays(2)->toDateString(), $now),
            $this->file(21004, 1003, 'Insurance', 'pending-insurance.pdf', $pdf, $now->copy()->addDays(20)->toDateString(), $now),
            $this->file(21005, 1002, 'Other', 'reference.pdf', $pdf, null, $now),
        ];
        $db->table('Files')->insert($files);
        $db->table('ActivityLogs')->insert([
            ['log_id' => 20001, 'user_id' => 1001, 'action' => 'M7 review fixture created', 'timestamp' => $now->toDateTimeString()],
            ['log_id' => 20002, 'user_id' => 1002, 'action' => 'Accepted job 2002', 'timestamp' => $now->copy()->subDay()->toDateTimeString()],
            ['log_id' => 20003, 'user_id' => 1003, 'action' => 'Pending contractor awaiting approval', 'timestamp' => $now->copy()->subDays(2)->toDateTimeString()],
        ]);

        $this->privateMaps($db, $png, $pdf, $now);

        return null;
    }

    private function reviewPdf(): string
    {
        $content = "BT /F1 18 Tf 50 740 Td (NestCrafters - synthetic review document) Tj ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content).">>\nstream\n".$content.'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function user(int $id, string $email, string $first, string $last, string $company, int $admin, int $approved, string $created): array
    {
        return [
            'user_id' => $id, 'company_name' => $company, 'company_address' => '100 Review Avenue, Local QA', 'company_phone' => '+1 555 010 '.$id,
            'first_name' => $first, 'last_name' => $last, 'phone1' => '+1 555 011 '.$id, 'phone1_type' => 'Mobile', 'phone2' => '', 'phone2_type' => 'Mobile',
            'email' => $email, 'password_hash' => password_hash('fixture-known-password', PASSWORD_BCRYPT, ['cost' => 10]), 'profile_photo' => null,
            'is_admin' => $admin, 'is_approved' => $approved, 'approved_date' => $approved === 1 ? $created : null, 'created_at' => $created, 'priority' => $admin ? 1 : 0,
        ];
    }

    private function job(int $id, string $name, string $due, ?int $owner, int $accepted, int $completed, string $description): array
    {
        return ['job_id' => $id, 'entered_datetime' => now()->subDays(10)->toDateTimeString(), 'due_date' => $due, 'job_name' => $name, 'job_description' => $description,
            'is_accepted' => $accepted, 'accepted_datetime' => $accepted ? now()->subDays(8)->toDateTimeString() : null, 'terms' => '<p>Fixture terms: confirm scope before work.</p>', 'contractor_id' => $owner,
            'is_completed' => $completed, 'completed_datetime' => $completed ? now()->subDays(3)->toDateTimeString() : null, 'approved_price' => '2450.00', 'deposit_amount' => '500.00', 'who_supplies_materials' => 'Admin'];
    }

    private function file(int $id, int $owner, string $type, string $name, string $bytes, ?string $expiry, $now): array
    {
        return ['file_id' => $id, 'user_id' => $owner, 'unique_file_id' => str_pad((string) $id, 32, 'f', STR_PAD_LEFT), 'file_type' => $type, 'file_name' => $name,
            'file_data' => $bytes, 'mime_type' => 'application/pdf', 'upload_date' => $now->toDateTimeString(), 'expiry_date' => $expiry, 'archived' => 0];
    }

    private function privateMaps($db, string $png, string $pdf, $now): void
    {
        $runId = (string) Str::ulid();
        $db->table('app_import_runs')->insert(['id' => $runId, 'source_sha256' => hash('sha256', 'm7-review-fixture-'.microtime(true)), 'text_encoding' => 'utf8', 'report' => json_encode(['fixture' => 'm7-review']), 'completed_at' => $now]);
        $root = 'm7-review';
        $maps = [
            ['table' => 'Users', 'id' => 1002, 'column' => 'profile_photo', 'owner' => 1002, 'name' => 'approved-profile.png', 'bytes' => $png, 'mime' => 'image/png'],
            ['table' => 'Files', 'id' => 21001, 'column' => 'file_data', 'owner' => 1002, 'name' => 'approved-w9.pdf', 'bytes' => $pdf, 'mime' => 'application/pdf'],
            ['table' => 'Files', 'id' => 21002, 'column' => 'file_data', 'owner' => 1002, 'name' => 'insurance-expiring-in-7-days.pdf', 'bytes' => $pdf, 'mime' => 'application/pdf'],
            ['table' => 'Files', 'id' => 21003, 'column' => 'file_data', 'owner' => 1002, 'name' => 'insurance-expired.pdf', 'bytes' => $pdf, 'mime' => 'application/pdf'],
            ['table' => 'Files', 'id' => 21004, 'column' => 'file_data', 'owner' => 1003, 'name' => 'pending-insurance.pdf', 'bytes' => $pdf, 'mime' => 'application/pdf'],
            ['table' => 'Files', 'id' => 21005, 'column' => 'file_data', 'owner' => 1002, 'name' => 'reference.pdf', 'bytes' => $pdf, 'mime' => 'application/pdf'],
            ['table' => 'Job_Media', 'id' => 20001, 'column' => 'file_data', 'owner' => 1002, 'name' => 'accepted-job-progress.png', 'bytes' => $png, 'mime' => 'image/png'],
        ];
        foreach ($maps as $map) {
            $sha = hash('sha256', $map['bytes']);
            $path = $root.'/'.$map['table'].'/'.$map['id'].'/'.$sha.'.bin';
            Storage::disk('private')->put($path, $map['bytes']);
            $db->table('app_private_files')->insert(['import_run_id' => $runId, 'source_table' => $map['table'], 'source_id' => $map['id'], 'source_column' => $map['column'], 'disk' => 'private', 'path' => $path, 'byte_size' => strlen($map['bytes']), 'sha256' => $sha, 'detected_mime_type' => $map['mime'], 'legacy_mime_type' => $map['mime'], 'original_name' => $map['name'], 'needs_review' => 0]);
        }

        // Synthetic inbox rows only: no delivery records, queued jobs or outgoing mail.
        foreach ([
            [1002, 'job.assigned', ['job_id' => 2001, 'actor_user_id' => 1001], false],
            [1002, 'job.updated', ['job_id' => 2002, 'actor_user_id' => 1001], true],
            [1002, 'contractor.approval_changed', ['actor_user_id' => 1001], false],
            [1001, 'job.accepted', ['job_id' => 2002, 'actor_user_id' => 1002], false],
            [1001, 'registration.created', [], true],
        ] as [$recipient, $type, $payload, $read]) {
            $event = NotificationEvent::unguarded(fn () => NotificationEvent::create([
                'type' => $type, 'subject_user_id' => 1002, 'payload' => $payload,
                'deduplication_key' => hash('sha256', 'm7-inbox:'.$recipient.':'.$type), 'created_at' => $now,
            ]));
            InboxNotification::unguarded(fn () => InboxNotification::create([
                'event_id' => $event->id, 'user_id' => $recipient, 'created_at' => $now,
                'read_at' => $read ? $now : null,
            ]));
        }
    }
}
