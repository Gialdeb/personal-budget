<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_employments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('annual_gross_salary', 14, 2)->nullable();
            $table->char('annual_gross_salary_currency', 3)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'start_date']);
        });
        Schema::create('salary_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('salary_employment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('label', 150);
            $table->timestamps();
        });
        Schema::create('salary_occurrence_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_entry_occurrence_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('salary_employment_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_schedule_id')->constrained()->restrictOnDelete();
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->decimal('base_expected_amount', 14, 2);
            $table->char('currency', 3);
            $table->boolean('is_overridden')->default(false);
            $table->timestamps();
            $table->index(['salary_employment_id', 'pay_period_start']);
        });
        Schema::create('salary_occurrence_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('salary_occurrence_detail_id')->constrained()->cascadeOnDelete();
            $table->string('label', 150);
            $table->decimal('amount', 14, 2);
            $table->string('type', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_occurrence_components');
        Schema::dropIfExists('salary_occurrence_details');
        Schema::dropIfExists('salary_schedules');
        Schema::dropIfExists('salary_employments');
    }
};
