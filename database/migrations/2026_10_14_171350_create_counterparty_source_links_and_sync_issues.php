<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparty_source_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relation_id')->constrained('counterparty_relations')->restrictOnDelete();
            $table->string('source_type', 40);
            $table->uuid('source_identity');
            $table->string('economic_identity', 191)->index();
            $table->timestamps();
            $table->unique(['source_type', 'source_identity'], 'cp_source_alias_unique');
        });
        Schema::create('counterparty_sync_issues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('source_type', 40);
            $table->uuid('source_identity');
            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_identity'], 'cp_sync_issue_source_unique');
            $table->index(['user_id', 'counterparty_id', 'currency_code'], 'cp_sync_issue_relation_index');
        });
        foreach (['update', 'delete'] as $operation) {
            $name = 'cp_source_links_no_'.$operation;
            match (DB::getDriverName()) {
                'sqlite' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON counterparty_source_links BEGIN SELECT RAISE(ABORT, 'Counterparty identity is immutable'); END"),
                'pgsql' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON counterparty_source_links FOR EACH ROW EXECUTE FUNCTION reject_counterparty_history_mutation()"),
                'mysql', 'mariadb' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON counterparty_source_links FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Counterparty identity is immutable'"),
                default => throw new RuntimeException('Unsupported database for counterparty identity protection.'),
            };
        }
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $operation) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::unprepared('DROP TRIGGER IF EXISTS cp_source_links_no_'.$operation);
            }
        }
        Schema::dropIfExists('counterparty_sync_issues');
        Schema::dropIfExists('counterparty_source_links');
    }
};
