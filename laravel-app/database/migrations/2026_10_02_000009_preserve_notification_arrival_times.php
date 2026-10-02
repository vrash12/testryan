<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notification_inbox', function (Blueprint $table): void {
            // An explicit default prevents older MariaDB from silently adding
            // ON UPDATE CURRENT_TIMESTAMP to the first TIMESTAMP column.
            $table->timestamp('created_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('app_notification_inbox', function (Blueprint $table): void {
            $table->timestamp('created_at')->useCurrent()->useCurrentOnUpdate()->change();
        });
    }
};
