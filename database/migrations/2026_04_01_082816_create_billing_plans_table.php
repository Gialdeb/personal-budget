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
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('grants_supporter_access')->default(false);
            $table->string('interval_unit', 20)->nullable();
            $table->unsignedInteger('duration_count')->nullable();
            $table->unsignedSmallInteger('reminder_days_before_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['grants_supporter_access', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};
