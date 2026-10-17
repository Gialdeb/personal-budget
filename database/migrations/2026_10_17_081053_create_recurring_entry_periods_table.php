<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_entry_amount_changes', function (Blueprint $table): void {
            $table->json('recurrence_comparison')->nullable();
        });
        Schema::create('recurring_entry_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_entry_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('legacy');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason')->nullable();
            $table->unsignedInteger('sequence_offset')->default(0);
            $table->json('terms');
            $table->timestamps();
            $table->index(['recurring_entry_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_entry_periods');
        Schema::table('recurring_entry_amount_changes', function (Blueprint $table): void {
            $table->dropColumn('recurrence_comparison');
        });
    }
};
