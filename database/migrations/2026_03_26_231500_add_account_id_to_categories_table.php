<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();

            $table->index(['account_id', 'parent_id', 'sort_order'], 'categories_account_parent_sort_index');
            $table->index(['account_id', 'is_active', 'is_selectable'], 'categories_account_active_selectable_index');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_account_parent_sort_index');
            $table->dropIndex('categories_account_active_selectable_index');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
