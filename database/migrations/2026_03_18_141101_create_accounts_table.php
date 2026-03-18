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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('account_type_id')->constrained('account_types')->restrictOnDelete();
            $table->foreignId('scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->string('name', 150);
            $table->string('iban', 34)->nullable();
            $table->string('account_number_masked', 50)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('opening_balance', 14, 2)->nullable();
            $table->decimal('current_balance', 14, 2)->nullable();
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'bank_id']);
            $table->index(['user_id', 'account_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
