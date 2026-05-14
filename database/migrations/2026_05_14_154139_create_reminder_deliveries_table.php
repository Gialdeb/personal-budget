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
        Schema::create('reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->foreignId('outbound_message_id')
                ->nullable()
                ->constrained('outbound_messages')
                ->nullOnDelete();
            $table->string('reminder_type');
            $table->date('due_date');
            $table->date('delivery_date');
            $table->string('notification_kind');
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'user_id',
                'remindable_type',
                'remindable_id',
                'reminder_type',
                'due_date',
                'delivery_date',
            ], 'reminder_deliveries_unique');
            $table->index(['user_id', 'notification_kind', 'delivery_date'], 'reminder_deliveries_user_kind_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_deliveries');
    }
};
