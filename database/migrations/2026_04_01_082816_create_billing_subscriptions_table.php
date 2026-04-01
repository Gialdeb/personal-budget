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
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_plan_id')->constrained('billing_plans')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('provider', 32)->default('manual');
            $table->boolean('is_supporter')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('last_transaction_id')->nullable();
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamp('next_reminder_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['status', 'is_supporter']);
            $table->index('provider');
            $table->index('ends_at');
            $table->index('next_reminder_at');
            $table->index('last_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
