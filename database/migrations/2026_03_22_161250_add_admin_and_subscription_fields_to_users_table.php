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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('locale');
            $table->text('status_reason')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');

            $table->string('plan_code', 32)->default('free')->after('status_changed_at');
            $table->string('subscription_status', 32)->default('active')->after('plan_code');
            $table->timestamp('subscription_started_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at');
            $table->boolean('is_impersonable')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'status_reason',
                'status_changed_at',
                'plan_code',
                'subscription_status',
                'subscription_started_at',
                'subscription_ends_at',
                'is_impersonable',
            ]);
        });
    }
};
