<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('device_identifier', 120)->nullable()->after('platform');
            $table->timestamp('last_registered_at')->nullable()->after('last_seen_at');
            $table->timestamp('invalidated_at')->nullable()->after('last_registered_at');
            $table->string('invalidation_reason', 120)->nullable()->after('invalidated_at');
            $table->string('service_worker_version', 64)->nullable()->after('invalidation_reason');

            $table->index(['user_id', 'platform', 'device_identifier'], 'device_tokens_user_platform_device_index');
            $table->index(['is_active', 'invalidated_at'], 'device_tokens_active_invalidated_index');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropIndex('device_tokens_user_platform_device_index');
            $table->dropIndex('device_tokens_active_invalidated_index');

            $table->dropColumn([
                'device_identifier',
                'last_registered_at',
                'invalidated_at',
                'invalidation_reason',
                'service_worker_version',
            ]);
        });
    }
};
