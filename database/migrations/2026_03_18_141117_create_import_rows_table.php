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
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->integer('row_index');
            $table->string('raw_date', 100)->nullable();
            $table->string('raw_value_date', 100)->nullable();
            $table->text('raw_description')->nullable();
            $table->string('raw_amount', 100)->nullable();
            $table->string('raw_balance', 100)->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->string('parse_status', 20)->default('pending');
            $table->text('parse_error')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'row_index']);
            $table->index(['import_id', 'parse_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
