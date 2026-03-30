<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('changelog_sections')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('label');
            $table->timestamps();

            $table->unique(['section_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_section_translations');
    }
};
