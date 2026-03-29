<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_items', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->cascadeOnDelete();
        });

        Schema::table('tracked_items', function (Blueprint $table): void {
            $table->dropUnique('tracked_items_user_id_slug_unique');
            $table->dropIndex('tracked_items_user_id_parent_id_index');
            $table->dropIndex('tracked_items_user_id_type_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX tracked_items_personal_user_slug_unique
            ON tracked_items (user_id, slug)
            WHERE account_id IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX tracked_items_account_slug_unique
            ON tracked_items (account_id, slug)
            WHERE account_id IS NOT NULL'
        );

        DB::statement(
            'CREATE INDEX tracked_items_personal_parent_index
            ON tracked_items (user_id, parent_id)
            WHERE account_id IS NULL'
        );

        DB::statement(
            'CREATE INDEX tracked_items_account_parent_index
            ON tracked_items (account_id, parent_id)
            WHERE account_id IS NOT NULL'
        );

        DB::statement(
            'CREATE INDEX tracked_items_personal_type_index
            ON tracked_items (user_id, type)
            WHERE account_id IS NULL'
        );

        DB::statement(
            'CREATE INDEX tracked_items_account_type_index
            ON tracked_items (account_id, type)
            WHERE account_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('tracked_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        DB::statement('DROP INDEX IF EXISTS tracked_items_personal_user_slug_unique');
        DB::statement('DROP INDEX IF EXISTS tracked_items_account_slug_unique');
        DB::statement('DROP INDEX IF EXISTS tracked_items_personal_parent_index');
        DB::statement('DROP INDEX IF EXISTS tracked_items_account_parent_index');
        DB::statement('DROP INDEX IF EXISTS tracked_items_personal_type_index');
        DB::statement('DROP INDEX IF EXISTS tracked_items_account_type_index');

        Schema::table('tracked_items', function (Blueprint $table): void {
            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'parent_id']);
            $table->index(['user_id', 'type']);
        });
    }
};
