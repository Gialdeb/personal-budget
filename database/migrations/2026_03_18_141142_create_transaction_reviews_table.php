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
        Schema::create('transaction_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->cascadeOnDelete();

            $table->foreignId('old_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('new_category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->foreignId('old_scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->foreignId('new_scope_id')->nullable()->constrained('scopes')->nullOnDelete();

            $table->foreignId('old_merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('new_merchant_id')->nullable()->constrained('merchants')->nullOnDelete();

            $table->string('review_action', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'review_action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_reviews');
    }
};
