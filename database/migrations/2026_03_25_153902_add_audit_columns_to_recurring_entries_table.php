<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('recurring_entries')->update([
            'created_by_user_id' => DB::raw('coalesce(created_by_user_id, user_id)'),
            'updated_by_user_id' => DB::raw('coalesce(updated_by_user_id, user_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('updated_by_user_id');
        });
    }
};
