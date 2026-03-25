<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
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
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('key')->unique();
            $table->foreignId('notification_topic_id')
                ->nullable()
                ->constrained('notification_topics')
                ->nullOnDelete();

            $table->string('channel')->default(CommunicationChannelEnum::MAIL->value);
            $table->string('template_mode')->default(CommunicationTemplateModeEnum::SYSTEM->value);

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('subject_template')->nullable();
            $table->string('title_template')->nullable();
            $table->longText('body_template');
            $table->string('cta_label_template')->nullable();
            $table->text('cta_url_template')->nullable();

            $table->boolean('is_system_locked')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('channel');
            $table->index('template_mode');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
    }
};
