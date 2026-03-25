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
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('household_id')->nullable()->after('user_id')->constrained('households')->nullOnDelete();
            $table->index(['household_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign('accounts_household_id_foreign');
            $table->dropIndex('accounts_household_id_foreign');
            $table->dropColumn('household_id');
        });
    }
};
