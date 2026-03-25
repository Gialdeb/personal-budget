<?php

use App\Enums\CommunicationChannelEnum;
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
        Schema::create('communication_category_channel_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('communication_category_id')
                ->constrained('communication_categories')
                ->cascadeOnDelete();

            $table->foreignId('communication_template_id')
                ->constrained('communication_templates')
                ->cascadeOnDelete();

            $table->string('channel')->default(CommunicationChannelEnum::MAIL->value);

            $table->boolean('is_default')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['communication_category_id', 'channel', 'communication_template_id'],
                'comm_category_channel_template_unique'
            );

            $table->index(
                ['communication_category_id', 'channel', 'is_default', 'is_active'],
                'comm_category_channel_template_lookup'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_category_channel_templates');
    }
};
