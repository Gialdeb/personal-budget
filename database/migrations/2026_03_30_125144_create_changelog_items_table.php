<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('changelog_sections')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('screenshot_key')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->string('item_type')->nullable();
            $table->string('platform')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_items');
    }
};
