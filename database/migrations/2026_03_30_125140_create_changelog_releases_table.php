<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_releases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('version_label')->unique();
            $table->unsignedInteger('version_major');
            $table->unsignedInteger('version_minor');
            $table->unsignedInteger('version_patch');
            $table->string('version_suffix')->nullable();
            $table->string('channel', 32)->default('stable');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->nullable();
            $table->timestamps();

            $table->index(['version_major', 'version_minor', 'version_patch']);
            $table->index(['channel', 'is_published']);
            $table->index(['is_pinned', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_releases');
    }
};
