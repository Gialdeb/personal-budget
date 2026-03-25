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
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();

            $table->string('email')->index();
            $table->string('role')->default('member');
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

            $table->index(['household_id', 'status']);
            $table->index(['email', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
