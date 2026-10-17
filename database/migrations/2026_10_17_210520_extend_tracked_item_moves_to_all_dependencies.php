<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_item_transaction_moves', function (Blueprint $table): void {
            $table->unsignedSmallInteger('scope_version')->default(1);
            $table->unsignedInteger('record_count')->nullable();
            $table->json('movement_counts')->nullable();
        });
        Schema::create('tracked_item_move_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('move_id')->constrained('tracked_item_transaction_moves')->restrictOnDelete();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid')->nullable();
            $table->json('before_values');
            $table->json('after_values');
            $table->char('after_fingerprint', 64);
            $table->char('context_fingerprint', 64)->nullable();
            $table->unique(['move_id', 'entity_type', 'entity_id'], 'tracked_item_move_record_unique');
            $table->index(['entity_type', 'entity_id'], 'tracked_item_move_record_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_item_move_records');
        Schema::table('tracked_item_transaction_moves', function (Blueprint $table): void {
            $table->dropColumn(['scope_version', 'record_count', 'movement_counts']);
        });
    }
};
