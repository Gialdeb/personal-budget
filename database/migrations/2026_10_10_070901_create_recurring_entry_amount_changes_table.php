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
        Schema::create('recurring_entry_amount_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_entry_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_amount', 18, 2);
            $table->decimal('new_amount', 18, 2);
            $table->date('effective_from');
            $table->string('currency', 3);
            $table->index(['recurring_entry_id', 'effective_from']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_entry_amount_changes');
    }
};
