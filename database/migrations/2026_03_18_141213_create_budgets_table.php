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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            $table->smallInteger('year');
            $table->unsignedSmallInteger('month');
            $table->decimal('amount', 14, 2);
            $table->string('budget_type', 20)->default('target');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'scope_id', 'category_id', 'year', 'month', 'budget_type'],
                'budgets_user_scope_category_year_month_type_unique'
            );

            $table->index(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
