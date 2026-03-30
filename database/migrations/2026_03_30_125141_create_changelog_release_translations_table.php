<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_release_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')
                ->constrained('changelog_releases')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('excerpt')->nullable();
            $table->timestamps();

            $table->unique(['release_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_release_translations');
    }
};
