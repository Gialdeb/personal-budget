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
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('automation_key');
            $table->string('pipeline');
            $table->string('job_class')->nullable();

            $table->string('status');
            $table->string('trigger_type');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();

            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            $table->string('batch_id')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->string('host')->nullable();

            $table->json('context')->nullable();
            $table->json('result')->nullable();

            $table->text('error_message')->nullable();
            $table->string('exception_class')->nullable();

            $table->timestamps();

            $table->index('automation_key');
            $table->index('pipeline');
            $table->index('status');
            $table->index('started_at');
            $table->index('finished_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
