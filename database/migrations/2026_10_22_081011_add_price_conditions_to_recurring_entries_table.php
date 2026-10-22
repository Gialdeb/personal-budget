<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table): void {
            $table->json('price_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table): void {
            $table->dropColumn('price_conditions');
        });
    }
};
