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
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('payment_verification_started_at')->nullable();
            $table->timestamp('payment_verification_due_at')->nullable()->index();
            $table->timestamp('payment_verified_at')->nullable();
            $table->string('payment_verification_method')->nullable();
        });
        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->timestamp('automatic_posting_blocked_at')->nullable();
            $table->timestamp('payment_failed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->dropColumn(['automatic_posting_blocked_at', 'payment_failed_at']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_verification_due_at']);
            $table->dropColumn(['payment_verification_started_at', 'payment_verification_due_at', 'payment_verified_at', 'payment_verification_method']);
        });
    }
};
