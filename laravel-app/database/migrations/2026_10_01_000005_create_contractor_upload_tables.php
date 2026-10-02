<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_private_uploads', function (Blueprint $table): void {
            $table->id();
            // Keep the owner link strict while source IDs remain compatible with the legacy tables.
            $table->integer('owner_user_id');
            $table->foreign('owner_user_id')->references('user_id')->on('Users')->restrictOnDelete();
            $table->string('source_table', 64);
            $table->unsignedInteger('source_id');
            $table->string('source_column', 64);
            $table->string('kind', 24);
            $table->string('disk', 50)->default('private');
            $table->string('path', 512);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('mime_type', 150);
            $table->string('original_name')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->index(['owner_user_id', 'kind']);
            $table->index(['source_table', 'source_id', 'source_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_private_uploads');
    }
};
