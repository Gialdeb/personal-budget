<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_debt_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('description', 255);
            $table->decimal('total_amount', 14, 2);
            $table->string('currency_code', 3);
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'reference_id']);
            $table->index(['user_id', 'account_id']);
            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_debt_items');
    }
};
