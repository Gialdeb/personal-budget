<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('normalized_name', 600)->index();
            $table->char('identity_key', 64)->unique();
            $table->json('aliases')->nullable();
            $table->text('search_text');
            $table->string('domain', 253)->index();
            $table->string('category', 100)->nullable()->index();
            $table->string('logo_path')->nullable();
            $table->string('logo_mime_type', 100)->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
