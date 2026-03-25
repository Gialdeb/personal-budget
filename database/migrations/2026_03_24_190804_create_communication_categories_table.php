<?php

use App\Enums\CommunicationDeliveryModeEnum;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
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
        Schema::create('communication_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('audience')->default(NotificationAudienceEnum::USER->value);
            $table->string('delivery_mode')->default(CommunicationDeliveryModeEnum::TRANSACTIONAL->value);
            $table->string('preference_mode')->default(NotificationPreferenceModeEnum::USER_CONFIGURABLE->value);

            $table->string('context_type');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('audience');
            $table->index('delivery_mode');
            $table->index('preference_mode');
            $table->index('context_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_categories');
    }
};
