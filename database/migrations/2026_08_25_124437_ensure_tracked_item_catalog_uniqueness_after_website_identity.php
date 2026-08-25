<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @noinspection SqlNoDataSourceInspection */
        DB::statement('DROP INDEX IF EXISTS tracked_items_user_id_slug_unique');
        /** @noinspection SqlNoDataSourceInspection */
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS tracked_items_personal_user_slug_unique
            ON tracked_items (user_id, slug)
            WHERE account_id IS NULL'
        );
        /** @noinspection SqlNoDataSourceInspection */
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS tracked_items_account_slug_unique
            ON tracked_items (account_id, slug)
            WHERE account_id IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // These indexes enforce the catalog invariant that predates this migration.
    }
};
