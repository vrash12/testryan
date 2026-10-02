<?php

use App\Actions\Import\LegacySchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('The legacy schema requires a MySQL/MariaDB migration connection.');
        }
        foreach (LegacySchema::KEYS as $table => $keys) {
            // Reviewed constant DDL, never request/dump-provided identifiers or expressions.
            $ddl = str_replace(['ENGINE=MyISAM', 'DEFAULT CHARSET=latin1'], ['ENGINE=InnoDB', 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'], LegacySchema::definitions()[$table]);
            DB::statement($ddl);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(LegacySchema::KEYS)) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
