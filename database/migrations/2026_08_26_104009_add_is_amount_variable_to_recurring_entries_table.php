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
            $table->boolean('is_amount_variable')->default(false)->after('expected_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->dropColumn('is_amount_variable');
        });
    }
};
