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
        Schema::create('household_memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();

            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('left_reason')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['household_id', 'user_id']);
            $table->index(['household_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('household_memberships');
    }
};
