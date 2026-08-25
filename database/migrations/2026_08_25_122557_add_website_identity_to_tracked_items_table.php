<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('tracked_items', function (Blueprint $table) {
                $table->unsignedBigInteger('website_identity_id')->nullable()->after('account_id');
                $table->text('website_url')->nullable()->after('type');
            });

            return;
        }

        Schema::table('tracked_items', function (Blueprint $table) {
            $table->foreignId('website_identity_id')
                ->nullable()
                ->after('account_id')
                ->constrained('website_identities')
                ->nullOnDelete();
            $table->text('website_url')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('tracked_items', function (Blueprint $table) {
                $table->dropColumn(['website_identity_id', 'website_url']);
            });

            return;
        }

        Schema::table('tracked_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_identity_id');
            $table->dropColumn('website_url');
        });
    }
};
