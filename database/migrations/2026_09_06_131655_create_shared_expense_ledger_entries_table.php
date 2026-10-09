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
        Schema::create('shared_expense_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained('tracked_items')->cascadeOnDelete();
            $table->foreignId('recurring_entry_occurrence_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('title');
            $table->string('currency', 3);
            $table->unsignedBigInteger('total_cents');
            $table->string('paid_by', 20);
            $table->unsignedBigInteger('my_share_cents');
            $table->unsignedBigInteger('counterparty_share_cents');
            $table->string('status', 20);
            $table->timestamps();
            $table->index(['user_id', 'counterparty_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_expense_ledger_entries');
    }
};
