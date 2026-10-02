<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notification_preferences', function (Blueprint $table): void {
            $table->integer('user_id');
            $table->string('event_type', 60);
            $table->boolean('email_enabled')->default(true);
            $table->primary(['user_id', 'event_type']);
            $table->foreign('user_id')->references('user_id')->on('Users')->cascadeOnDelete();
        });
        Schema::create('app_notification_inbox', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('app_notification_events')->cascadeOnDelete();
            $table->integer('user_id');
            $table->foreign('user_id')->references('user_id')->on('Users')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->timestamp('read_at')->nullable();
            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notification_inbox');
        Schema::dropIfExists('app_notification_preferences');
    }
};
