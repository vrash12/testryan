<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_account_states', function (Blueprint $table): void {
            $table->integer('user_id')->primary();
            $table->foreign('user_id')->references('user_id')->on('Users')->restrictOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verified_email')->nullable();
            $table->unsignedInteger('session_version')->default(0);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('registration_signals')->nullable();
            $table->char('registration_ip_hash', 64)->nullable();
        });
        // Imported accounts keep their approval/passwords; email proof is never inferred.
        DB::table('app_account_states')->insertUsing(['user_id'], DB::table('Users')->select('user_id'));
        Schema::create('app_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('app_notification_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 60);
            $table->integer('subject_user_id');
            $table->foreign('subject_user_id')->references('user_id')->on('Users')->restrictOnDelete();
            $table->char('deduplication_key', 64)->unique();
            $table->text('payload'); // Laravel encrypted cast; URLs/tokens stay private.
            $table->timestamp('created_at');
        });
        Schema::create('app_notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('app_notification_events')->restrictOnDelete();
            $table->integer('recipient_user_id');
            $table->foreign('recipient_user_id')->references('user_id')->on('Users')->restrictOnDelete();
            $table->string('recipient_email');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unique(['event_id', 'recipient_user_id']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        foreach (['app_notification_deliveries', 'app_notification_events', 'app_password_reset_tokens', 'app_account_states'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
