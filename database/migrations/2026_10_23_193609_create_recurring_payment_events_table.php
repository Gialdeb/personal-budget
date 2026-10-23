<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('occurrence_id')->constrained('recurring_entry_occurrences')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason');
            $table->string('action');
            $table->text('notes')->nullable();
            $table->json('details');
            $table->timestamps();
            $table->index(['occurrence_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_payment_events');
    }
};
