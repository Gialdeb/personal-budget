<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_entry_occurrences', function (Blueprint $table): void {
            $table->string('payment_management_state')->nullable()->index();
            $table->date('payment_estimated_date')->nullable();
            $table->date('payment_reminder_date')->nullable()->index();
            $table->timestamp('payment_resolved_at')->nullable();
            $table->decimal('payment_actual_amount', 15, 2)->nullable();
            $table->date('payment_actual_date')->nullable();
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('payment_original_amount', 15, 2)->nullable();
            $table->timestamp('payment_rectified_at')->nullable();
            $table->text('payment_rectification_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn(['payment_original_amount', 'payment_rectified_at', 'payment_rectification_reason']));
        Schema::table('recurring_entry_occurrences', fn (Blueprint $table) => $table->dropColumn(['payment_management_state', 'payment_estimated_date', 'payment_reminder_date', 'payment_resolved_at', 'payment_actual_amount', 'payment_actual_date']));
    }
};
