<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('changelog_items')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title')->nullable();
            $table->longText('body');
            $table->timestamps();

            $table->unique(['item_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_item_translations');
    }
};
