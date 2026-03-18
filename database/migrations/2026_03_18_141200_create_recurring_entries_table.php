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
        Schema::create('recurring_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();

            $table->string('title', 150);
            $table->text('description')->nullable();

            $table->string('direction', 20);
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->string('recurrence_type', 20);
            $table->integer('recurrence_interval')->default(1);
            $table->text('recurrence_rule')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('due_day')->nullable();

            $table->boolean('auto_generate_occurrences')->default(true);
            $table->boolean('auto_create_transaction')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['account_id', 'start_date']);
            $table->index(['recurrence_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_entries');
    }
};
