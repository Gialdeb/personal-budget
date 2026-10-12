<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->timestamp('inactivity_warned_at')->nullable();
            $table->timestamp('auto_close_at')->nullable();
            $table->index(['status', 'last_admin_reply_at']);
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropIndex(['status', 'last_admin_reply_at']);
            $table->dropColumn(['inactivity_warned_at', 'auto_close_at']);
        });
    }
};
