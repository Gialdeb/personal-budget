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
        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();

            $table->string('email')->index();
            $table->string('role')->default('viewer');
            $table->json('permissions')->nullable();

            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('token_hash', 128);
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();

            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['email', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
    }
};
