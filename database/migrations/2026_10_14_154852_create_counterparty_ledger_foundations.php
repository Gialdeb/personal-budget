<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparty_relations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('counterparty_id')->constrained('tracked_items')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->unsignedTinyInteger('minor_unit');
            $table->string('migration_state', 20)->default('shadow');
            $table->timestamps();
            $table->unique(['user_id', 'counterparty_id', 'currency_code'], 'cp_relation_identity_unique');
            $table->unique(['id', 'currency_code'], 'cp_relation_currency_unique');
        });
        Schema::create('counterparty_obligations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relation_id')->constrained('counterparty_relations')->restrictOnDelete();
            $table->string('source_type', 40);
            $table->string('source_identity', 191);
            $table->string('economic_identity', 191);
            $table->bigInteger('recognized_minor');
            $table->date('economic_date');
            $table->date('due_date')->nullable();
            $table->foreignId('revision_of_id')->nullable()->constrained('counterparty_obligations')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['relation_id', 'economic_identity'], 'cp_obligation_economic_unique');
            $table->unique(['relation_id', 'source_type', 'source_identity'], 'cp_obligation_source_unique');
            $table->unique(['id', 'relation_id'], 'cp_obligation_relation_unique');
            $table->index(['relation_id', 'economic_date']);
        });
        Schema::create('counterparty_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relation_id')->constrained('counterparty_relations')->restrictOnDelete();
            $table->enum('direction', ['received', 'sent']);
            $table->unsignedBigInteger('amount_minor');
            $table->date('economic_date');
            /** Documentary links intentionally survive the legacy force-delete lifecycle. */
            $table->unsignedBigInteger('account_id');
            $table->uuid('account_uuid');
            $table->unsignedBigInteger('transaction_id')->nullable()->unique();
            $table->uuid('transaction_uuid')->nullable();
            $table->string('status', 20)->default('recorded');
            $table->string('legacy_source_type', 40)->nullable();
            $table->string('legacy_source_identity', 191)->nullable();
            $table->string('idempotency_key', 191);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('revision_of_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['relation_id', 'idempotency_key'], 'cp_payment_idempotency_unique');
            $table->unique(['legacy_source_type', 'legacy_source_identity'], 'cp_payment_legacy_unique');
            $table->unique(['id', 'relation_id'], 'cp_payment_relation_unique');
            $table->foreign(['revision_of_id', 'relation_id'], 'cp_payment_revision_fk')->references(['id', 'relation_id'])->on('counterparty_payments')->restrictOnDelete();
        });
        Schema::create('counterparty_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relation_id')->constrained('counterparty_relations')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('obligation_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->bigInteger('signed_minor');
            $table->enum('event_type', ['obligation_recognized', 'obligation_revised', 'payment_recorded', 'compensation', 'reversal']);
            $table->string('source_type', 40);
            $table->string('source_identity', 191);
            $table->unsignedInteger('source_version');
            $table->string('idempotency_key', 191);
            $table->char('payload_hash', 64);
            $table->date('economic_date');
            $table->timestamp('recorded_at');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->unique();
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->uuid('transaction_uuid')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['relation_id', 'idempotency_key'], 'cp_ledger_idempotency_unique');
            $table->unique(['relation_id', 'source_type', 'source_identity', 'source_version', 'event_type'], 'cp_ledger_source_version_unique');
            $table->unique(['id', 'relation_id'], 'cp_ledger_relation_unique');
            $table->foreign(['relation_id', 'currency_code'], 'cp_ledger_currency_fk')->references(['id', 'currency_code'])->on('counterparty_relations')->restrictOnDelete();
            $table->foreign(['obligation_id', 'relation_id'], 'cp_ledger_obligation_fk')->references(['id', 'relation_id'])->on('counterparty_obligations')->restrictOnDelete();
            $table->foreign(['payment_id', 'relation_id'], 'cp_ledger_payment_fk')->references(['id', 'relation_id'])->on('counterparty_payments')->restrictOnDelete();
            $table->foreign(['reversal_of_id', 'relation_id'], 'cp_ledger_reversal_fk')->references(['id', 'relation_id'])->on('counterparty_ledger_entries')->restrictOnDelete();
            $table->index(['relation_id', 'economic_date', 'id'], 'cp_ledger_economic_index');
        });
        Schema::create('counterparty_allocation_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relation_id')->constrained('counterparty_relations')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('compensation_entry_id')->nullable();
            $table->unsignedBigInteger('obligation_id');
            $table->bigInteger('amount_minor');
            $table->date('economic_date');
            $table->timestamp('recorded_at');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->unique();
            $table->string('idempotency_key', 191);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['relation_id', 'idempotency_key'], 'cp_allocation_idempotency_unique');
            $table->unique(['id', 'relation_id'], 'cp_allocation_relation_unique');
            $table->foreign(['relation_id', 'currency_code'], 'cp_allocation_currency_fk')->references(['id', 'currency_code'])->on('counterparty_relations')->restrictOnDelete();
            $table->foreign(['obligation_id', 'relation_id'], 'cp_allocation_obligation_fk')->references(['id', 'relation_id'])->on('counterparty_obligations')->restrictOnDelete();
            $table->foreign(['payment_id', 'relation_id'], 'cp_allocation_payment_fk')->references(['id', 'relation_id'])->on('counterparty_payments')->restrictOnDelete();
            $table->foreign(['compensation_entry_id', 'relation_id'], 'cp_allocation_compensation_fk')->references(['id', 'relation_id'])->on('counterparty_ledger_entries')->restrictOnDelete();
            $table->foreign(['reversal_of_id', 'relation_id'], 'cp_allocation_reversal_fk')->references(['id', 'relation_id'])->on('counterparty_allocation_entries')->restrictOnDelete();
        });
        $this->protectHistory();
    }

    private function protectHistory(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION reject_counterparty_history_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'Counterparty history is append-only'; END; $$ LANGUAGE plpgsql");
        }
        foreach (['counterparty_obligations', 'counterparty_payments', 'counterparty_ledger_entries', 'counterparty_allocation_entries'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                $name = $table.'_no_'.$operation;
                match ($driver) {
                    'sqlite' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Counterparty history is append-only'); END"),
                    'pgsql' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW EXECUTE FUNCTION reject_counterparty_history_mutation()"),
                    'mysql', 'mariadb' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Counterparty history is append-only'"),
                    default => throw new RuntimeException('Unsupported database for append-only counterparty history.'),
                };
            }
        }
    }

    public function down(): void
    {
        foreach (['counterparty_allocation_entries', 'counterparty_ledger_entries', 'counterparty_payments', 'counterparty_obligations', 'counterparty_relations'] as $table) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");
            }
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS reject_counterparty_history_mutation()');
        }
    }
};
