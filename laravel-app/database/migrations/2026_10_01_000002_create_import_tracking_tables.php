<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_import_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('source_sha256', 64)->unique();
            $table->string('text_encoding', 20);
            $table->json('report');
            $table->timestamp('completed_at');
        });
        Schema::create('app_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('import_run_id')->constrained('app_import_runs')->restrictOnDelete();
            $table->string('source_table', 64);
            $table->string('record_key', 150);
            $table->char('source_sha256', 64);
            $table->char('target_sha256', 64);
            $table->unique(['source_table', 'record_key']);
        });
        Schema::create('app_private_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('import_run_id')->constrained('app_import_runs')->restrictOnDelete();
            $table->string('source_table', 64);
            $table->integer('source_id');
            $table->string('source_column', 64);
            $table->string('disk', 50);
            $table->string('path', 512);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('detected_mime_type', 150);
            $table->string('legacy_mime_type', 150)->nullable();
            $table->string('original_name')->nullable();
            $table->boolean('needs_review');
            $table->unique(['source_table', 'source_id', 'source_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_private_files');
        Schema::dropIfExists('app_import_rows');
        Schema::dropIfExists('app_import_runs');
    }
};
