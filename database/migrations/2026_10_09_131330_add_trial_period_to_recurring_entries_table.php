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
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->unsignedInteger('trial_duration')->nullable();
            $table->string('trial_unit', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->dropColumn(['trial_duration', 'trial_unit']);
        });
    }
};
