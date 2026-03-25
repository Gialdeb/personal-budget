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
        Schema::create('account_memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();

            $table->string('role')->default('viewer');
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();

            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source')->default('direct');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('left_reason')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
            $table->index(['account_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_memberships');
    }
};
