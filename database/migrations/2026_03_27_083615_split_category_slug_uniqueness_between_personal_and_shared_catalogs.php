<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS categories_user_id_slug_unique');
        } else {
            DB::statement('ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_user_id_slug_unique');
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS categories_personal_user_slug_unique
            ON categories (user_id, slug)
            WHERE account_id IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS categories_shared_account_slug_unique
            ON categories (account_id, slug)
            WHERE account_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS categories_personal_user_slug_unique');
        DB::statement('DROP INDEX IF EXISTS categories_shared_account_slug_unique');

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS categories_user_id_slug_unique
                ON categories (user_id, slug)
            ');
        } else {
            DB::statement('
                ALTER TABLE categories
                ADD CONSTRAINT categories_user_id_slug_unique UNIQUE (user_id, slug)
            ');
        }
    }
};
