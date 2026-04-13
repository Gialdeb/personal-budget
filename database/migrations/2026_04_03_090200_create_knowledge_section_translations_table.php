<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('knowledge_sections')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_section_translations');
    }
};
