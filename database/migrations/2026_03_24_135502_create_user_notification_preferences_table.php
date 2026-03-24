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
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_topic_id')->constrained('notification_topics')->cascadeOnDelete();

            $table->boolean('email_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'notification_topic_id'], 'user_notification_topic_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
