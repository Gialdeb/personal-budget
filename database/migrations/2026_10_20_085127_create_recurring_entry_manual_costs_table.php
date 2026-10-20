<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_entry_manual_costs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recurring_entry_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->timestamps();
            $table->unique(['recurring_entry_id', 'date', 'amount', 'currency'], 'recurring_manual_cost_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_entry_manual_costs');
    }
};
