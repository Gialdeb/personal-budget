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
        Schema::create('scheduled_entries', function (Blueprint $table) {
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

            $table->date('scheduled_date');
            $table->string('status', 20);

            $table->foreignId('matched_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_date']);
            $table->index(['status', 'scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_entries');
    }
};
