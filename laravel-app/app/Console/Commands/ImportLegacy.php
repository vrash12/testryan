<?php

namespace App\Console\Commands;

use App\Actions\Import\LegacyImport;
use App\Actions\Import\SqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportLegacy extends Command
{
    protected $signature = 'nestcrafters:import-legacy {dump : Absolute path to the local SQL reference}';

    protected $description = 'Restore and reconcile legacy data in explicitly allowed local databases';

    public function handle(LegacyImport $import): int
    {
        try {
            $dump = new SqlDump($this->argument('dump'));
            $report = $import->run($dump, DB::connection(config('legacy.source_connection')), DB::connection(config('legacy.target_connection')));
            try {
                Storage::disk('private')->put('imports/'.$dump->sha256.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } catch (Throwable $exception) {
                Log::warning('Import verified, but the report file could not be saved.', ['exception' => $exception]);
                $this->warn('Data verified; the private report file is unavailable. The database import ledger retains the report.');
            }
            foreach ($report['counts'] as $table => $count) {
                $this->line($table.': '.$count);
            }
            $this->info($report['mode'].'; private files: '.$report['private_files'].'; preserved orphan logs: '.$report['preserved_orphan_logs']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Local legacy import failed.', ['exception' => $exception]);
            $this->error('Import stopped. Existing target edits were not overwritten. Details are in private logs.');

            return self::FAILURE;
        }
    }
}
