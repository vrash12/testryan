<?php

use App\Actions\Import\LegacySchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (LegacySchema::REFERENCES as [$child, $column, $parent, $key]) {
            Schema::table($child, function (Blueprint $table) use ($column, $parent, $key): void {
                $table->foreign($column)->references($key)->on($parent)->restrictOnDelete()->restrictOnUpdate();
            });
        }
        Schema::table('Files', fn (Blueprint $table) => $table->unique('unique_file_id'));
    }

    public function down(): void
    {
        Schema::table('Files', fn (Blueprint $table) => $table->dropUnique(['unique_file_id']));
        foreach (array_reverse(LegacySchema::REFERENCES) as [$child, $column]) {
            Schema::table($child, fn (Blueprint $table) => $table->dropForeign([$column]));
        }
    }
};
