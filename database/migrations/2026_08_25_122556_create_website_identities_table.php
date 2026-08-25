<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_identities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('domain', 253)->unique();
            $table->text('canonical_url');
            $table->string('logo_path')->nullable();
            $table->string('logo_mime_type', 100)->nullable();
            $table->text('logo_source_url')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_identities');
    }
};
