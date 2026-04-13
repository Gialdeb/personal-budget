<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_article_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')
                ->constrained('knowledge_articles')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->timestamps();

            $table->unique(['article_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_article_translations');
    }
};
