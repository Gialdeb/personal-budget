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
        Schema::table('categories', function (Blueprint $table) {
            $table->index(['user_id', 'parent_id', 'sort_order'], 'categories_user_parent_sort_index');
            $table->index(['user_id', 'is_active', 'is_selectable'], 'categories_user_active_selectable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_user_parent_sort_index');
            $table->dropIndex('categories_user_active_selectable_index');
        });
    }
};
