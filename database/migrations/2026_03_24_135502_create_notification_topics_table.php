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
        Schema::create('notification_topics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('audience');

            $table->boolean('supports_email')->default(false);
            $table->boolean('supports_in_app')->default(true);
            $table->boolean('supports_sms')->default(false);

            $table->boolean('default_email_enabled')->default(false);
            $table->boolean('default_in_app_enabled')->default(true);
            $table->boolean('default_sms_enabled')->default(false);

            $table->boolean('is_user_configurable')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('audience');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_topics');
    }
};
