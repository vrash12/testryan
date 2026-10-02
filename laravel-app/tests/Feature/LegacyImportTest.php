<?php

namespace Tests\Feature;

use App\Actions\Import\LegacyImport;
use App\Actions\Import\LegacySchema;
use App\Actions\Import\LocalImportGuard;
use App\Actions\Import\PrivateFileCopy;
use App\Actions\Import\SqlDump;
use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Enums\MediaTag;
use App\Models\ActivityLog;
use App\Models\ContractorDocument;
use App\Models\ContractorNote;
use App\Models\Job;
use App\Models\JobMedia;
use App\Models\JobNote;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    private Connection $target;

    private Connection $source;

    private string $path;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        if (! config('database.connections.m2_test.username')) {
            $this->markTestSkipped('Dedicated local MySQL M2 test credentials are not configured.');
        }
        config(['database.default' => 'm2_test', 'legacy.source_connection' => 'm2_source_test', 'legacy.target_connection' => 'm2_test']);
        $this->target = DB::connection('m2_test');
        $this->source = DB::connection('m2_source_test');
        app(LocalImportGuard::class)->check($this->source, $this->target); // Required before any disposable-schema mutation.
        $this->artisan('migrate:fresh', ['--database' => 'm2_test', '--force' => true])->assertSuccessful();
        foreach (array_keys(LegacySchema::KEYS) as $table) {
            Schema::connection('m2_source_test')->dropIfExists($table);
        }
        Storage::fake('private');
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->path = tempnam(storage_path('framework/testing'), 'legacy-fixture-');
        $this->fixture = LegacyFixture::rows();
        LegacyFixture::write($this->path, $this->fixture);
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    private function import(): array
    {
        return app(LegacyImport::class)->run(new SqlDump($this->path), $this->source, $this->target);
    }

    public function test_native_restore_target_and_private_copies_preserve_every_fixture_value(): void
    {
        $createdEvents = 0;
        User::created(function () use (&$createdEvents): void {
            $createdEvents++;
        });
        $report = $this->import();
        foreach ($this->fixture as $table => $rows) {
            $this->assertSame(count($rows), $this->source->table($table)->count());
            $this->assertSame(count($rows), $this->target->table($table)->count());
            foreach ($rows as $row) {
                $query = $this->target->table($table);
                foreach (LegacySchema::KEYS[$table] as $key) {
                    $query->where($key, $row[$key]);
                }
                $actual = (array) $query->first();
                foreach ($row as $column => $value) {
                    $this->assertSame(LegacySchema::canonical($table, $column, $value), LegacySchema::canonical($table, $column, $actual[$column]), $table.'.'.$column);
                }
            }
        }
        $this->assertSame('imported', $report['mode']);
        $this->assertSame(1, $report['preserved_orphan_logs']);
        $this->assertSame(3, $report['private_files']);
        foreach ($this->target->table('app_private_files')->get() as $file) {
            $original = collect($this->fixture[$file->source_table])->first(fn ($row) => $row[LegacySchema::KEYS[$file->source_table][0]] == $file->source_id)[$file->source_column];
            $this->assertSame($original, Storage::disk('private')->get($file->path));
        }
        $this->assertSame(0, $createdEvents);
        $this->assertSame(3, $this->target->table('app_account_states')->count());
        $this->assertSame(0, $this->target->table('app_account_states')->whereNotNull('email_verified_at')->count());
        $this->assertSame(0, $this->target->table('app_notification_events')->count());
        $this->assertSame(0, $this->target->table('app_notification_deliveries')->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_repeat_import_creates_no_duplicates_and_preserves_counters(): void
    {
        $this->import();
        $before = Storage::disk('private')->allFiles();
        $report = $this->import();
        $this->assertSame('verified-rerun', $report['mode']);
        $this->assertSame(1, $this->target->table('app_import_runs')->count());
        $this->assertSame(3, $this->target->table('app_private_files')->count());
        $this->assertSame(3, $this->target->table('app_account_states')->count());
        $this->assertSame($before, Storage::disk('private')->allFiles());
        foreach (LegacySchema::KEYS as $table => $keys) {
            $meta = $this->target->selectOne('SELECT engine, auto_increment FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = ?', [strtolower($table)]);
            $this->assertSame('InnoDB', $meta->engine);
            $this->assertSame(LegacySchema::nextId($table), $meta->auto_increment === null ? null : (int) $meta->auto_increment);
        }
    }

    public function test_repeat_import_refuses_to_overwrite_later_local_edits(): void
    {
        $this->import();
        $this->target->table('Jobs')->where('job_id', 4)->update(['job_name' => 'Later local edit']);
        try {
            $this->import();
            $this->fail('Changed target rows must be detected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('row/byte mismatch', $exception->getMessage());
        }
        $this->assertSame('Later local edit', $this->target->table('Jobs')->where('job_id', 4)->value('job_name'));
    }

    public function test_changed_private_copy_is_detected_and_not_silently_replaced(): void
    {
        $this->import();
        $path = $this->target->table('app_private_files')->value('path');
        Storage::disk('private')->put($path, 'changed-copy');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private file is missing or changed');
        $this->import();
    }

    public function test_storage_failure_rolls_back_all_target_rows_and_only_removes_new_files(): void
    {
        Storage::disk('private')->put('existing.txt', 'keep');
        $failing = new class extends PrivateFileCopy
        {
            public function copy(string $dumpHash, string $table, array $row): array
            {
                parent::copy($dumpHash, $table, $row);
                throw new RuntimeException('Synthetic disk failure');
            }
        };
        try {
            (new LegacyImport(app(LocalImportGuard::class), $failing))->run(new SqlDump($this->path), $this->source, $this->target);
            $this->fail('Disk failure must stop the import.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic disk failure', $exception->getMessage());
        }
        foreach (array_keys(LegacySchema::KEYS) as $table) {
            $this->assertSame(0, $this->target->table($table)->count());
        }
        $this->assertSame(0, $this->target->table('app_import_runs')->count());
        $this->assertSame(['existing.txt'], Storage::disk('private')->allFiles());
        $this->assertSame('keep', Storage::disk('private')->get('existing.txt'));
        $this->assertSame(3, $this->source->table('Users')->count());
    }

    public function test_partial_native_staging_restore_resumes_without_overwriting_verified_rows(): void
    {
        $dump = new SqlDump($this->path);
        foreach (LegacySchema::definitions() as $ddl) {
            $this->source->statement($ddl);
        }
        $this->source->unprepared($dump->statements[0][2]);
        $this->assertSame(1, $this->source->table($dump->statements[0][0])->count());
        $this->import();
        $this->assertSame(3, $this->source->table('Users')->count());
        $this->assertSame(3, $this->target->table('Users')->count());
    }

    public function test_overlapping_import_is_refused_and_releases_its_other_lock(): void
    {
        $other = DB::build(array_replace($this->target->getConfig(), ['name' => 'm2_lock_contender']));
        $this->assertNotSame($this->target->selectOne('SELECT CONNECTION_ID() AS id')->id, $other->selectOne('SELECT CONNECTION_ID() AS id')->id);
        $targetLock = 'nestcrafters:m2:'.substr(hash('sha256', $this->target->getDatabaseName()), 0, 48);
        $sourceLock = 'nestcrafters:m2:'.substr(hash('sha256', $this->source->getDatabaseName()), 0, 48);
        $this->assertSame(1, (int) $other->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$targetLock])->acquired);
        try {
            $this->import();
            $this->fail('An overlapping import must not restore or copy files.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Another legacy import', $exception->getMessage());
            $this->assertNull($this->source->selectOne('SELECT IS_USED_LOCK(?) AS owner', [$sourceLock])->owner);
            $this->assertFalse(Schema::connection('m2_source_test')->hasTable('Users'));
            $this->assertSame(0, User::count());
            $this->assertSame([], Storage::disk('private')->allFiles());
        } finally {
            $other->selectOne('SELECT RELEASE_LOCK(?) AS released', [$targetLock]);
            $other->disconnect();
        }
        $this->assertSame('imported', $this->import()['mode']);
        $this->assertNull($this->target->selectOne('SELECT IS_USED_LOCK(?) AS owner', [$targetLock])->owner);
    }

    public function test_changed_staging_row_is_detected_without_overwriting_either_database(): void
    {
        $this->import();
        $this->source->table('Services')->where('service_id', 3)->update(['service_name' => 'Changed staging record']);
        try {
            $this->import();
            $this->fail('A changed staging row must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Staging contains a changed/unexpected row', $exception->getMessage());
        }
        $this->assertSame('Changed staging record', $this->source->table('Services')->where('service_id', 3)->value('service_name'));
        $this->assertSame($this->fixture['Services'][0]['service_name'], Service::findOrFail(3)->service_name);
        $this->assertSame(1, $this->target->table('app_import_runs')->count());
    }

    public function test_models_follow_legacy_keys_relationships_and_hidden_file_fields(): void
    {
        $this->import();
        $contractor = User::findOrFail(2);
        $this->assertSame(ApprovalStatus::PendingAdminReview, $contractor->approvalStatus());
        $this->assertSame('contractor', $contractor->role());
        $this->assertNull(User::findOrFail(3)->email);
        $this->assertSame(3, $contractor->services->first()->service_id);
        $this->assertSame(4, $contractor->jobs->first()->job_id);
        $this->assertSame(2, Service::findOrFail(3)->contractors->first()->user_id);
        $job = Job::findOrFail(4);
        $this->assertSame(2, $job->contractor->user_id);
        $this->assertSame(12, $job->services->first()->pivot->job_service_id);
        $this->assertSame(4, Service::findOrFail(3)->jobs->first()->job_id);
        $this->assertSame('1234.05', $job->approved_price);
        $this->assertSame('0.00', $job->deposit_amount);
        $this->assertNull($job->terms);
        $this->assertSame(JobStatus::Completed, $job->status());
        $this->assertSame('2025-01-03 10:00:00', $job->accepted_datetime->format('Y-m-d H:i:s'));
        $this->assertSame(1, $job->notes->count());
        $this->assertSame(1, $job->media->count());
        $this->assertSame(2, JobNote::findOrFail(1)->author->user_id);
        $this->assertSame(4, JobNote::findOrFail(1)->job->job_id);
        $this->assertSame(1, ContractorNote::findOrFail(1)->adminAuthor->user_id);
        $this->assertSame(2, ContractorNote::findOrFail(1)->contractor->user_id);
        $media = JobMedia::findOrFail(5);
        $this->assertSame(MediaTag::During, $media->tag);
        $this->assertSame(2, $media->uploader->user_id);
        $this->assertSame(4, $media->job->job_id);
        $this->assertNotNull($media->privateFile);
        $document = ContractorDocument::findOrFail(2);
        $this->assertSame(2, $document->owner->user_id);
        $this->assertNotNull($document->privateFile);
        $this->assertNotNull($contractor->privatePhoto);
        $this->assertArrayNotHasKey('password_hash', $contractor->toArray());
        $this->assertArrayNotHasKey('profile_photo', $contractor->toArray());
        $this->assertArrayNotHasKey('file_data', $document->toArray());
        $this->assertArrayNotHasKey('path', $document->privateFile->toArray());
        $this->assertArrayNotHasKey('file_data', $media->toArray());
        $this->assertNull(ActivityLog::findOrFail(2)->associatedUser);
        $this->assertSame(11, ActivityLog::findOrFail(2)->user_id);
        $this->assertSame('2025-01-01 01:02:03', Template::findOrFail(2)->created_at->format('Y-m-d H:i:s'));
    }

    public function test_existing_bcrypt_hashes_are_unchanged_and_compatible(): void
    {
        $this->import();
        $user = User::findOrFail(2);
        $hash = $this->fixture['Users'][1]['password_hash'];
        $this->assertSame($hash, $user->getAuthPassword());
        $this->assertTrue(password_verify('fixture-known-password', $user->getAuthPassword()));
        $this->assertSame('user_id', $user->getAuthIdentifierName());
        $this->assertNull($user->getRememberTokenName());
        $this->assertFalse(Schema::hasColumn('Users', 'email_verified_at'));
    }

    public function test_foreign_keys_are_signed_int_restrictive_and_orphan_logs_are_retained(): void
    {
        $this->import();
        $column = $this->target->selectOne("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = 'users' AND column_name = 'user_id'");
        $this->assertStringNotContainsString('unsigned', $column->column_type);
        $this->assertStringStartsWith('int', $column->column_type);
        $this->assertSame(11, $this->target->table('ActivityLogs')->where('log_id', 2)->value('user_id'));
        try {
            $this->target->table('Jobs')->where('job_id', 4)->update(['contractor_id' => 999]);
            $this->fail('Foreign key must reject an absent contractor.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }
        try {
            User::findOrFail(2)->delete();
            $this->fail('Referenced user deletion must be restricted.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }
        $this->assertSame(3, User::count());
    }

    public function test_existing_models_remain_editable_and_multirecord_transactions_roll_back(): void
    {
        $this->import();
        $user = User::findOrFail(2);
        $original = $user->getRawOriginal('company_name');
        $created = $user->getRawOriginal('created_at');
        $this->target->beginTransaction();
        $user->company_name = 'Temporary editable record';
        $user->save();
        $user->services()->detach(3);
        $this->assertSame('Temporary editable record', $user->fresh()->company_name);
        $this->assertSame($created, $user->fresh()->getRawOriginal('created_at'));
        $this->assertSame(0, $user->fresh()->services->count());
        $this->target->rollBack();
        $this->assertSame($original, $user->fresh()->company_name);
        $this->assertSame(1, $user->fresh()->services->count());
    }

    public function test_migrations_roll_back_and_rebuild_on_disposable_mysql_schema(): void
    {
        $this->import();
        $this->artisan('migrate:rollback', ['--database' => 'm2_test', '--step' => count(glob(database_path('migrations/*.php'))), '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('Users'));
        $this->assertFalse(Schema::hasTable('app_private_files'));
        $this->artisan('migrate', ['--database' => 'm2_test', '--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('Users'));
        $this->assertSame(0, User::count());
        $this->import();
        $this->assertSame(3, User::count());
    }

    public function test_import_command_refuses_production_environment_before_source_mutation(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('nestcrafters:import-legacy', ['dump' => $this->path])->assertFailed();
        $this->assertFalse(Schema::connection('m2_source_test')->hasTable('Users'));
        $this->assertSame(0, User::count());
    }
}
