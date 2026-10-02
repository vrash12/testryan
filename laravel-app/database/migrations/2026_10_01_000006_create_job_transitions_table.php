<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_job_transitions', function (Blueprint $table): void {
            $table->id();
            $table->integer('job_id');
            $table->integer('actor_user_id')->nullable();
            $table->string('action', 40);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->json('from_state')->nullable();
            $table->json('to_state')->nullable();
            $table->integer('from_contractor_id')->nullable();
            $table->integer('to_contractor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['job_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_job_transitions');
    }
};
