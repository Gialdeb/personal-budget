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
        Schema::table('salary_schedules', function (Blueprint $table) {
            Schema::table('salary_schedules', function (Blueprint $table): void {
                $table->foreignId('parent_schedule_id')->nullable()->constrained('salary_schedules')->restrictOnDelete();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_schedules', function (Blueprint $table) {
            Schema::table('salary_schedules', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('parent_schedule_id');
                $table->dropColumn(['valid_from', 'valid_to']);
            });
        });
    }
};
