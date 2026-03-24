<?php

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
        Schema::table('notification_topics', function (Blueprint $table) {
            $table->string('preference_mode')
                ->default(NotificationPreferenceModeEnum::USER_CONFIGURABLE->value)
                ->after('default_sms_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_topics', function (Blueprint $table) {
            $table->dropColumn('preference_mode');
        });
    }
};
