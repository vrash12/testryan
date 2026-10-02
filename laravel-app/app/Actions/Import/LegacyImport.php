<?php

namespace App\Actions\Import;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class LegacyImport
{
    public function __construct(private LocalImportGuard $guard, private PrivateFileCopy $files) {}

    public function run(SqlDump $dump, Connection $source, Connection $target): array
    {
        $this->guard->check($source, $target);
        $locks = [];
        try {
            foreach ([$source, $target] as $connection) {
                $name = 'nestcrafters:m2:'.substr(hash('sha256', $connection->getDatabaseName()), 0, 48);
                if ((int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$name])->acquired !== 1) {
                    throw new RuntimeException('Another legacy import is using this local database. Retry after it finishes.');
                }
                $locks[] = [$connection, $name];
            }

            return $this->import($dump, $source, $target);
        } finally {
            foreach (array_reverse($locks) as [$connection, $name]) {
                try {
                    $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
                } catch (Throwable $exception) {
                    Log::warning('Local import lock release failed; closing the connection releases it.', ['exception' => $exception]);
                    $connection->disconnect();
                }
            }
        }
    }

    private function import(SqlDump $dump, Connection $source, Connection $target): array
    {
        $largest = max(array_map(fn ($item) => strlen($item[2]), $dump->statements) ?: [0]);
        foreach ([$source, $target] as $connection) {
            if ((int) $connection->selectOne('SELECT @@max_allowed_packet AS bytes')->bytes < $largest + 65536) {
                throw new RuntimeException('Local MariaDB max_allowed_packet is too small; configure at least 32 MiB and reconnect.');
            }
        }
        // Connection-scoped, not a server/global setting. Binary BLOBs never undergo text conversion.
        $source->statement('SET NAMES latin1');
        $source->statement("SET time_zone = '+00:00'");
        $source->statement("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        $target->statement('SET NAMES utf8mb4');
        $target->statement("SET time_zone = '+00:00'");
        $this->validateText($dump);
        $this->restoreSource($dump, $source);
        $this->verifyRows($dump, $source);
        $this->verifySourceMetadata($source);
        $report = $this->report($dump, $source);
        $run = $target->table('app_import_runs')->where('source_sha256', $dump->sha256)->first();
        if ($run) {
            $this->verifyTarget($dump, $target, $run->id);

            return $report + ['mode' => 'verified-rerun', 'private_files' => $target->table('app_private_files')->count()];
        }
        foreach (LegacySchema::KEYS as $table => $keys) {
            if ($target->table($table)->exists()) {
                throw new RuntimeException('Target contains records without a matching import ledger. Nothing was overwritten.');
            }
        }
        try {
            Model::withoutEvents(fn () => $target->transaction(function () use ($dump, $target, &$report): void {
                $runId = (string) Str::ulid();
                $target->table('app_import_runs')->insert([
                    'id' => $runId, 'source_sha256' => $dump->sha256, 'text_encoding' => 'utf8',
                    'report' => json_encode($report, JSON_THROW_ON_ERROR), 'completed_at' => now(),
                ]);
                $fileCount = 0;
                foreach ($dump->rows as $table => $rows) {
                    foreach ($rows as $key => $row) {
                        // Prepared inserts preserve NULL, cents, hashes, historical HTML and literal dates.
                        $target->table($table)->insert($row);
                        $target->table('app_import_rows')->insert([
                            'import_run_id' => $runId, 'source_table' => $table, 'record_key' => $key,
                            'source_sha256' => LegacySchema::checksum($table, $row),
                            'target_sha256' => LegacySchema::checksum($table, $row),
                        ]);
                        if (isset(LegacySchema::BLOBS[$table]) && $row[LegacySchema::BLOBS[$table][0]] !== null) {
                            $metadata = $this->files->copy($dump->sha256, $table, $row);
                            $target->table('app_private_files')->insert($metadata + ['import_run_id' => $runId]);
                            $fileCount++;
                        }
                    }
                }
                if ($target->getSchemaBuilder()->hasTable('app_account_states')) {
                    // M3 may be migrated before a first import on a new machine. No email proof or mail is inferred.
                    $target->table('app_account_states')->insertUsing(['user_id'], $target->table('Users')->select('user_id'));
                }
                $report['private_files'] = $fileCount;
                $this->verifyTarget($dump, $target, $runId);
                $target->table('app_import_runs')->where('id', $runId)->update(['report' => json_encode($report, JSON_THROW_ON_ERROR)]);
            }));
        } catch (Throwable $exception) {
            $this->files->rollback();
            throw $exception;
        }
        $this->files->commit();
        $report['private_files'] = $target->table('app_private_files')->count();

        return $report + ['mode' => 'imported'];
    }

    private function validateText(SqlDump $dump): void
    {
        foreach ($dump->rows as $table => $rows) {
            foreach ($rows as $row) {
                foreach ($row as $column => $value) {
                    if ($value !== null && ! in_array($column, LegacySchema::BLOBS[$table] ?? [], true) && ! mb_check_encoding((string) $value, 'UTF-8')) {
                        throw new RuntimeException('Non-UTF8 legacy text needs an explicit encoding review; no conversion was guessed.');
                    }
                }
            }
        }
    }

    private function restoreSource(SqlDump $dump, Connection $source): void
    {
        $tables = $source->select('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
        if ($tables !== []) {
            $expected = array_map('strtolower', array_keys(LegacySchema::KEYS));
            $actual = array_map(fn ($row) => strtolower($row->table_name), $tables);
            sort($expected);
            sort($actual);
            if ($actual !== $expected) {
                throw new RuntimeException('Staging is not empty and does not contain exactly the legacy tables.');
            }
        } else {
            foreach (LegacySchema::definitions() as $ddl) {
                $source->statement($ddl);
            }
        }
        $existing = [];
        foreach ($dump->rows as $table => $expected) {
            foreach ($source->table($table)->get() as $record) {
                $row = (array) $record;
                $key = LegacySchema::rowKey($table, $row);
                if (! isset($expected[$key]) || LegacySchema::checksum($table, $row) !== LegacySchema::checksum($table, $expected[$key])) {
                    throw new RuntimeException('Staging contains a changed/unexpected row; nothing was overwritten.');
                }
                $existing[$table][$key] = true;
            }
        }
        // Native SQL interpretation independently checks the parser's escape handling.
        // DROP, foreign-key toggles, unreviewed DDL and arbitrary expressions are never executed.
        foreach ($dump->statements as [$table, $key, $statement]) {
            if (! isset($existing[$table][$key])) {
                $source->unprepared($statement);
            }
        }
    }

    private function verifyRows(SqlDump $dump, Connection $connection): void
    {
        foreach ($dump->rows as $table => $expected) {
            $actual = $connection->table($table)->select(array_keys(LegacySchema::columns($table)))->get();
            if (count($expected) !== count($actual)) {
                throw new RuntimeException('Imported row count mismatch in '.$table.'.');
            }
            foreach ($actual as $record) {
                $row = (array) $record;
                $key = LegacySchema::rowKey($table, $row);
                if (! isset($expected[$key]) || LegacySchema::checksum($table, $expected[$key]) !== LegacySchema::checksum($table, $row)) {
                    throw new RuntimeException('Imported row/byte mismatch in '.$table.'.');
                }
            }
        }
    }

    private function verifySourceMetadata(Connection $source): void
    {
        foreach (LegacySchema::KEYS as $table => $keys) {
            $meta = $source->selectOne('SELECT engine, table_collation, auto_increment FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = ?', [strtolower($table)]);
            if ($meta->engine !== 'MyISAM' || $meta->table_collation !== 'latin1_swedish_ci' || ($meta->auto_increment !== null && (int) $meta->auto_increment !== LegacySchema::nextId($table))) {
                throw new RuntimeException('Restored staging metadata differs from the legacy schema.');
            }
        }
    }

    private function verifyTarget(SqlDump $dump, Connection $target, string $runId): void
    {
        $this->verifyRows($dump, $target);
        $expectedLedgerRows = array_sum(array_map('count', $dump->rows));
        if ($target->table('app_import_rows')->where('import_run_id', $runId)->count() !== $expectedLedgerRows) {
            throw new RuntimeException('Import ledger is incomplete.');
        }
        foreach (LegacySchema::KEYS as $table => $keys) {
            $meta = $target->selectOne('SELECT engine, table_collation, auto_increment FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = ?', [strtolower($table)]);
            if ($meta->engine !== 'InnoDB' || $meta->table_collation !== 'utf8mb4_unicode_ci' || ($meta->auto_increment !== null && (int) $meta->auto_increment !== LegacySchema::nextId($table))) {
                throw new RuntimeException('Target engine, charset or next ID differs from its migration contract.');
            }
        }
        foreach ($target->table('app_import_rows')->where('import_run_id', $runId)->get() as $ledger) {
            $expected = $dump->rows[$ledger->source_table][$ledger->record_key] ?? null;
            if ($expected === null || $ledger->source_sha256 !== LegacySchema::checksum($ledger->source_table, $expected) || $ledger->target_sha256 !== $ledger->source_sha256) {
                throw new RuntimeException('Import ledger checksum differs from the immutable source.');
            }
        }
        $expectedFiles = 0;
        foreach (LegacySchema::BLOBS as $table => [$column]) {
            foreach ($dump->rows[$table] as $row) {
                if ($row[$column] !== null) {
                    $expectedFiles++;
                }
            }
        }
        $metadata = $target->table('app_private_files')->where('import_run_id', $runId)->get();
        if (count($metadata) !== $expectedFiles) {
            throw new RuntimeException('Private file mapping count mismatch.');
        }
        foreach ($metadata as $file) {
            $row = $dump->rows[$file->source_table][json_encode([(string) $file->source_id])];
            if (hash('sha256', $row[$file->source_column]) !== $file->sha256) {
                throw new RuntimeException('Private file mapping differs from its source BLOB.');
            }
            $this->files->verify((array) $file);
        }
    }

    private function report(SqlDump $dump, Connection $source): array
    {
        $orphans = $source->table('ActivityLogs')->leftJoin('Users', 'ActivityLogs.user_id', '=', 'Users.user_id')->whereNull('Users.user_id')->count();
        $rowChecksums = [];
        foreach ($dump->rows as $table => $rows) {
            $checksums = array_map(fn ($row) => LegacySchema::checksum($table, $row), $rows);
            ksort($checksums);
            $rowChecksums[$table] = hash('sha256', implode('', $checksums));
        }

        return [
            'source_sha256' => $dump->sha256, 'counts' => array_map('count', $dump->rows),
            'table_sha256' => $rowChecksums, 'preserved_orphan_logs' => $orphans,
            'text_encoding' => 'UTF-8 dump literals; original raw bytes retained in latin1 staging',
            'timestamp_session' => '+00:00, exported calendar values retained; original business timezone unknown',
        ];
    }
}
