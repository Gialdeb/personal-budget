<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_item_transaction_moves', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Audit identities survive deletion of the original objects; no cascading foreign keys.
            $table->unsignedBigInteger('catalog_user_id')->index();
            $table->unsignedBigInteger('catalog_account_id')->nullable()->index();
            $table->unsignedBigInteger('source_id');
            $table->uuid('source_uuid');
            $table->string('source_name');
            $table->unsignedBigInteger('destination_id');
            $table->uuid('destination_uuid');
            $table->string('destination_name');
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->unsignedInteger('transaction_count');
            $table->timestampTz('created_at', 6);
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('restored_at', 6)->nullable();
            $table->unsignedBigInteger('restored_by_id')->nullable();
            $table->string('restored_by_name')->nullable();
        });
        Schema::create('tracked_item_transaction_move_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('move_id')->constrained('tracked_item_transaction_moves')->restrictOnDelete();
            $table->unsignedBigInteger('transaction_id')->index();
            $table->uuid('transaction_uuid');
            $table->unsignedBigInteger('previous_tracked_item_id');
            $table->unsignedBigInteger('applied_tracked_item_id');
            $table->char('after_fingerprint', 64);
            $table->unique(['move_id', 'transaction_id'], 'tracked_item_move_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_item_transaction_move_entries');
        Schema::dropIfExists('tracked_item_transaction_moves');
    }
};
