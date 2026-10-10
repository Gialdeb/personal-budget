<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 64);
            $table->string('source_key', 128);
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('archived_at');
            $table->unsignedSmallInteger('payload_version');
            $table->string('payload_codec', 32);
            $table->char('checksum', 64);
            $table->longText('payload');
            $table->unique(['source_table', 'source_key']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('technical_archives') && DB::table('technical_archives')->exists()) {
            throw new RuntimeException('Cannot drop a populated technical archive. Preserve its contents before rolling back.');
        }

        Schema::dropIfExists('technical_archives');
    }
};
