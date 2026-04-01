<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_subscriptions', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('next_reminder_at');
            }
        });

        Schema::table('billing_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_transactions', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('billing_subscriptions', 'admin_notes')) {
                $table->dropColumn('admin_notes');
            }
        });

        Schema::table('billing_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('billing_transactions', 'admin_notes')) {
                $table->dropColumn('admin_notes');
            }
        });
    }
};
