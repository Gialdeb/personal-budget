<?php

use App\Support\PublicUuidRollout;
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
        foreach (PublicUuidRollout::domainTables() as $table => $indexName) {
            if (! Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->uuid('uuid')->nullable();
                });
            }
        }

        PublicUuidRollout::backfillAll();

        foreach (PublicUuidRollout::domainTables() as $table => $indexName) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->unique('uuid', $indexName);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(PublicUuidRollout::domainTables(), true) as $table => $indexName) {
            if (! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropUnique($indexName);
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
