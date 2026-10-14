<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counterparty_relations', function (Blueprint $table): void {
            $table->timestamp('activated_at')->nullable();
            $table->json('activation_snapshot')->nullable();
        });
        Schema::table('counterparty_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('return_of_id')->nullable();
            $table->foreign(['return_of_id', 'relation_id'], 'cp_payment_return_fk')->references(['id', 'relation_id'])->on('counterparty_payments')->restrictOnDelete();
        });
        $this->restoreSqlitePaymentTriggers();
        Schema::create('counterparty_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relation_id')->constrained('counterparty_relations')->restrictOnDelete();
            $table->string('operation_key', 191);
            $table->char('payload_hash', 64);
            $table->string('kind', 40);
            $table->unsignedBigInteger('actor_id');
            $table->json('result');
            $table->timestamps();
            $table->unique(['relation_id', 'operation_key'], 'cp_operation_idempotency_unique');
        });
        foreach (['update', 'delete'] as $operation) {
            $name = 'cp_operations_no_'.$operation;
            match (DB::getDriverName()) {
                'sqlite' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON counterparty_operations BEGIN SELECT RAISE(ABORT, 'Counterparty operations are append-only'); END"),
                'pgsql' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON counterparty_operations FOR EACH ROW EXECUTE FUNCTION reject_counterparty_history_mutation()"),
                'mysql', 'mariadb' => DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON counterparty_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Counterparty operations are append-only'"),
                default => throw new RuntimeException('Unsupported counterparty operation history driver.'),
            };
        }
    }

    private function restoreSqlitePaymentTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS counterparty_payments_no_{$operation} BEFORE {$operation} ON counterparty_payments BEGIN SELECT RAISE(ABORT, 'Counterparty history is append-only'); END");
            }
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS cp_operations_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS cp_operations_no_delete');
        }
        Schema::dropIfExists('counterparty_operations');
        Schema::table('counterparty_payments', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['return_of_id', 'relation_id'] : 'cp_payment_return_fk');
            $table->dropColumn('return_of_id');
        });
        $this->restoreSqlitePaymentTriggers();
        Schema::table('counterparty_relations', function (Blueprint $table): void {
            $table->dropColumn(['activated_at', 'activation_snapshot']);
        });
    }
};
