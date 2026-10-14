<?php

use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Counterparties\CounterpartyProjectionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Requires an isolated PostgreSQL test database for real row-lock concurrency.');
    }
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    config(['counterparty_ledger.public_enabled' => true]);
});

test('concurrent public payment workers serialize duplicate keys and reject simultaneous overpayment', function (bool $sameKey) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Credito', 'total_amount' => '100.00', 'currency_code' => 'EUR']);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $relation = CounterpartyRelation::query()->firstOrFail();
    app(CounterpartyPaymentService::class)->activate($user, $relation, 'activate');
    $bootstrap = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); config(["counterparty_ledger.public_enabled"=>true]);';
    $jobs = [];
    $barrier = microtime(true) + 1;
    foreach (['a', $sameKey ? 'a' : 'b'] as $operationKey) {
        $input = ['operation_key' => $operationKey, 'amount' => '70.00', 'date' => '2026-10-14', 'direction' => 'received', 'account_uuid' => $account->uuid];
        $code = $bootstrap.'while(microtime(true)<'.sprintf('%.6F', $barrier).'){usleep(10000);} try { $result=app(App\\Services\\Counterparties\\CounterpartyPaymentService::class)->create(App\\Models\\User::findOrFail('.$user->id.'),App\\Models\\CounterpartyRelation::findOrFail('.$relation->id.'),json_decode('.var_export(json_encode($input), true).',true)); echo json_encode(["ok"=>true,"uuid"=>$result["payment_uuid"]]); } catch (Illuminate\\Validation\\ValidationException $e) { echo json_encode(["ok"=>false,"errors"=>$e->errors()]); }';
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => config('database.connections.pgsql.database'), 'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false'], timeout: 30);
        $process->start();
        $jobs[] = $process;
    }
    $results = [];
    foreach ($jobs as $job) {
        $job->wait();
        expect($job->isSuccessful())->toBeTrue($job->getErrorOutput());
        $results[] = json_decode($job->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
    expect(CounterpartyPayment::count())->toBe(1)->and(Transaction::count())->toBe(1)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(3000);
    if ($sameKey) {
        expect($results[0]['ok'])->toBeTrue()->and($results[1]['ok'])->toBeTrue()->and($results[0]['uuid'])->toBe($results[1]['uuid']);
    } else {
        expect(array_column($results, 'ok'))->toContain(true, false);
    }
})->with(['same idempotency key' => true, 'competing payments on the same residual' => false]);

test('a legacy request waiting on the counterparty lock cannot write after concurrent activation', function (string $domain) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $item = CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Credito', 'total_amount' => '200.00', 'currency_code' => 'EUR']);
    SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'entry_date' => '2026-10-01', 'title' => 'Cena', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'self', 'status' => 'recorded']);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $relation = CounterpartyRelation::query()->firstOrFail();
    $workerName = 'legacy_activation_'.fake()->uuid();
    $bootstrap = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); config(["features.credits_debts.enabled"=>true,"counterparty_ledger.public_enabled"=>true]); Illuminate\\Support\\Facades\\DB::select("select set_config(?, ?, false)",["application_name",'.var_export($workerName, true).']); $user=App\\Models\\User::findOrFail('.$user->id.');';
    $call = $domain === 'manual'
        ? 'app(App\\Services\\CreditDebts\\CreditDebtPaymentService::class)->create($user,App\\Models\\CreditDebtItem::findOrFail('.$item->id.'),["amount"=>"10.00","paid_at"=>"2026-10-14","account_id"=>'.$account->id.']);'
        : 'app(App\\Services\\SharedExpenses\\SharedExpenseSettlementService::class)->record($user,App\\Models\\TrackedItem::findOrFail('.$party->id.'),["amount"=>"10.00","settled_at"=>"2026-10-14","account_uuid"=>'.var_export($account->uuid, true).']);';
    $code = $bootstrap.'try {'.$call.'echo "unexpected-write";} catch(Illuminate\\Validation\\ValidationException $e) {echo json_encode($e->errors());}';
    $worker = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => config('database.connections.pgsql.database'), 'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false'], timeout: 30);
    DB::beginTransaction();
    try {
        TrackedItem::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();
        $worker->start();
        $waiting = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = (bool) DB::selectOne('select exists(select 1 from pg_stat_activity where application_name = ? and wait_event_type = ?) as waiting', [$workerName, 'Lock'])->waiting;
            if ($waiting) {
                break;
            }
            usleep(50000);
        }
        expect($waiting)->toBeTrue('Legacy worker must actually wait on the held counterparty row lock. '.$worker->getErrorOutput());
        app(CounterpartyPaymentService::class)->activate($user, $relation, 'activate-during-request');
        DB::commit();
        $worker->wait();
        expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput())->and($worker->getOutput())->toContain('counterparty')->not->toContain('unexpected-write')
            ->and(CreditDebtPayment::count())->toBe(0)->and(SharedExpenseSettlement::count())->toBe(0)->and(CounterpartyPayment::count())->toBe(0)->and(Transaction::count())->toBe(0)
            ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(25000);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $worker->stop();
    }
})->with(['manual', 'shared']);
