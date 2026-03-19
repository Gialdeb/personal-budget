<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tracked_item_id')->constrained('tracked_items')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tracked_item_id', 'category_id']);
            $table->index(['category_id', 'tracked_item_id']);
        });

        DB::table('tracked_items')
            ->select(['id', 'settings'])
            ->orderBy('id')
            ->each(function (object $trackedItem): void {
                $settings = is_array($trackedItem->settings)
                    ? $trackedItem->settings
                    : json_decode((string) $trackedItem->settings, true);

                $categoryIds = collect($settings['transaction_category_ids'] ?? [])
                    ->filter(fn ($value): bool => is_numeric($value))
                    ->map(fn ($value): int => (int) $value)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($categoryIds as $categoryId) {
                    DB::table('tracked_item_categories')->insertOrIgnore([
                        'tracked_item_id' => (int) $trackedItem->id,
                        'category_id' => $categoryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_item_categories');
    }
};
