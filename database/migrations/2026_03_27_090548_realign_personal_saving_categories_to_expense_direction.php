<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->whereNull('account_id')
            ->where('foundation_key', 'saving')
            ->update([
                'direction_type' => 'expense',
                'group_type' => 'saving',
            ]);

        $categories = DB::table('categories')
            ->select(['id', 'parent_id', 'foundation_key'])
            ->whereNull('account_id')
            ->orderBy('id')
            ->get();

        $childrenByParent = $categories->groupBy('parent_id');
        $savingRootIds = $categories
            ->where('foundation_key', 'saving')
            ->pluck('id')
            ->all();

        $descendantIds = collect($savingRootIds)
            ->flatMap(function (int $rootId) use ($childrenByParent): array {
                $stack = [$rootId];
                $resolved = [];

                while ($stack !== []) {
                    $current = array_pop($stack);

                    /** @var Collection<int, object> $children */
                    $children = $childrenByParent->get($current, collect());

                    foreach ($children as $child) {
                        $resolved[] = $child->id;
                        $stack[] = $child->id;
                    }
                }

                return $resolved;
            })
            ->unique()
            ->values()
            ->all();

        if ($descendantIds !== []) {
            DB::table('categories')
                ->whereIn('id', $descendantIds)
                ->update([
                    'direction_type' => 'expense',
                    'group_type' => 'saving',
                ]);
        }
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereNull('account_id')
            ->where('foundation_key', 'saving')
            ->update([
                'direction_type' => 'transfer',
                'group_type' => 'saving',
            ]);
    }
};
