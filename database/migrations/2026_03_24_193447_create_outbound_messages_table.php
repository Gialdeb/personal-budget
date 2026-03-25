<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
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
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('communication_category_id')
                ->constrained('communication_categories')
                ->cascadeOnDelete();

            $table->foreignId('communication_template_id')
                ->nullable()
                ->constrained('communication_templates')
                ->nullOnDelete();

            $table->string('channel')->default(CommunicationChannelEnum::MAIL->value);
            $table->string('status')->default(OutboundMessageStatusEnum::DRAFT->value);

            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');

            $table->string('context_type');
            $table->unsignedBigInteger('context_id');

            $table->string('subject_resolved')->nullable();
            $table->string('title_resolved')->nullable();
            $table->longText('body_resolved');
            $table->string('cta_label_resolved')->nullable();
            $table->text('cta_url_resolved')->nullable();

            $table->json('payload_snapshot')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id'], 'outbound_messages_recipient_idx');
            $table->index(['context_type', 'context_id'], 'outbound_messages_context_idx');
            $table->index(['channel', 'status'], 'outbound_messages_channel_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
