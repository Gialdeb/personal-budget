<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')
                ->constrained('changelog_releases')
                ->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['release_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_sections');
    }
};
