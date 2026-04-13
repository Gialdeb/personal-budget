<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contextual_help_entry_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contextual_help_entry_id')
                ->constrained('contextual_help_entries')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title');
            $table->longText('body');
            $table->timestamps();

            $table->unique(['contextual_help_entry_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contextual_help_entry_translations');
    }
};
