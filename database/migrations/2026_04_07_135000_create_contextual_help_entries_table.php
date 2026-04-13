<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contextual_help_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('page_key')->unique();
            $table->foreignId('knowledge_article_id')
                ->nullable()
                ->constrained('knowledge_articles')
                ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contextual_help_entries');
    }
};
