<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('foundation_key', 50)->nullable()->after('slug');
            $table->boolean('is_system')->default(false)->after('is_selectable');

            $table->unique(['user_id', 'foundation_key'], 'categories_user_foundation_key_unique');
        });

        $foundations = [
            [
                'foundation_key' => 'income',
                'name' => 'Entrate',
                'slug' => 'entrate',
                'icon' => 'circle-dollar-sign',
                'color' => '#15803d',
                'direction_type' => 'income',
                'group_type' => 'income',
                'sort_order' => 1,
            ],
            [
                'foundation_key' => 'expense',
                'name' => 'Spese',
                'slug' => 'spese',
                'icon' => 'credit-card',
                'color' => '#e11d48',
                'direction_type' => 'expense',
                'group_type' => 'expense',
                'sort_order' => 2,
            ],
            [
                'foundation_key' => 'bill',
                'name' => 'Bollette',
                'slug' => 'bollette',
                'icon' => 'receipt',
                'color' => '#1d4ed8',
                'direction_type' => 'expense',
                'group_type' => 'bill',
                'sort_order' => 3,
            ],
            [
                'foundation_key' => 'debt',
                'name' => 'Debiti',
                'slug' => 'debiti',
                'icon' => 'hand-coins',
                'color' => '#7c3aed',
                'direction_type' => 'expense',
                'group_type' => 'debt',
                'sort_order' => 4,
            ],
            [
                'foundation_key' => 'saving',
                'name' => 'Risparmi',
                'slug' => 'risparmi',
                'icon' => 'piggy-bank',
                'color' => '#ca8a04',
                'direction_type' => 'transfer',
                'group_type' => 'saving',
                'sort_order' => 5,
            ],
        ];

        $timestamp = now();
        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            foreach ($foundations as $foundation) {
                $existingId = DB::table('categories')
                    ->where('user_id', $userId)
                    ->where(function ($query) use ($foundation): void {
                        $query
                            ->where('foundation_key', $foundation['foundation_key'])
                            ->orWhere('slug', $foundation['slug']);
                    })
                    ->value('id');

                if ($existingId !== null) {
                    DB::table('categories')
                        ->where('id', $existingId)
                        ->update([
                            'parent_id' => null,
                            'foundation_key' => $foundation['foundation_key'],
                            'name' => $foundation['name'],
                            'slug' => $foundation['slug'],
                            'direction_type' => $foundation['direction_type'],
                            'group_type' => $foundation['group_type'],
                            'icon' => $foundation['icon'],
                            'color' => $foundation['color'],
                            'sort_order' => $foundation['sort_order'],
                            'is_active' => true,
                            'is_selectable' => true,
                            'is_system' => true,
                            'updated_at' => $timestamp,
                        ]);

                    continue;
                }

                DB::table('categories')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'parent_id' => null,
                    'name' => $foundation['name'],
                    'slug' => $foundation['slug'],
                    'foundation_key' => $foundation['foundation_key'],
                    'direction_type' => $foundation['direction_type'],
                    'group_type' => $foundation['group_type'],
                    'color' => $foundation['color'],
                    'icon' => $foundation['icon'],
                    'sort_order' => $foundation['sort_order'],
                    'is_active' => true,
                    'is_selectable' => true,
                    'is_system' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_user_foundation_key_unique');
            $table->dropColumn(['foundation_key', 'is_system']);
        });
    }
};
