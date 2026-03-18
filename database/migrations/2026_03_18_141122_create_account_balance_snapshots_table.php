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
        Schema::create('account_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('balance', 14, 2);
            $table->string('source_type', 20);
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'snapshot_date']);
            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balance_snapshots');
    }
};
