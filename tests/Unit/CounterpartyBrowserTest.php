<?php

use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('real desktop and mobile users register revise allocate and cancel a common payment through both sections', function (int $width) {
    if (! getenv('COUNTERPARTY_BROWSER_MODULE_PATH')) {
        $this->markTestSkipped('Opt in with a local Playwright module path; requires built frontend assets.');
    }
    $database = tempnam(sys_get_temp_dir(), 'counterparty-browser-');
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'counterparty_ledger.public_enabled' => true]);
    DB::purge('sqlite');
    $server = null;
    try {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $user = User::factory()->create(['email' => 'counterparty-browser@example.test', 'locale' => 'it', 'base_currency_code' => 'EUR']);
        $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
        createTestAccount($user, ['name' => 'Conto test', 'opening_balance' => 1000, 'current_balance' => 1000]);
        $item = CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Credito iniziale', 'total_amount' => '200.00', 'currency_code' => 'EUR']);
        $item->forceFill(['created_at' => '2026-09-01'])->save();
        SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'entry_date' => '2026-10-01', 'title' => 'Cena', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'self', 'status' => 'recorded']);
        app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
        $relation = CounterpartyRelation::query()->firstOrFail();
        app(CounterpartyPaymentService::class)->activate($user, $relation, 'activate');
        $compParty = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela compensazione', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
        CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $compParty->id, 'type' => 'credit', 'description' => 'Credito da compensare', 'total_amount' => '200.00', 'currency_code' => 'EUR']);
        SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $compParty->id, 'entry_date' => '2026-10-01', 'title' => 'Debito condiviso', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'counterparty', 'status' => 'recorded']);
        app(CounterpartyBackfillService::class)->run($user, $compParty, 'EUR', true);
        $compRelation = CounterpartyRelation::query()->where('counterparty_id', $compParty->id)->firstOrFail();
        app(CounterpartyPaymentService::class)->activate($user, $compRelation, 'activate-compensation');
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $url = 'http://127.0.0.1:'.$port;
        $environment = ['APP_ENV' => 'testing', 'APP_URL' => $url, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'database', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false', 'BROADCAST_CONNECTION' => 'log', 'QUEUE_CONNECTION' => 'sync', 'NIGHTWATCH_ENABLED' => 'false', 'COUNTERPARTY_LEDGER_PUBLIC_ENABLED' => 'true', 'FEATURE_CREDITS_DEBTS_ENABLED' => 'true', 'RECAPTCHA_V3_ENABLED' => 'false'];
        $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload'], base_path(), $environment, timeout: 120);
        $server->start();
        $browser = new Process(['node', base_path('tests/frontend/counterparty-browser.mjs')], base_path(), ['COUNTERPARTY_BROWSER_URL' => $url, 'COUNTERPARTY_BROWSER_RELATION' => $relation->uuid, 'COUNTERPARTY_BROWSER_COMPENSATION' => $compRelation->uuid, 'COUNTERPARTY_BROWSER_WIDTH' => (string) $width], timeout: 100);
        $browser->run();
        expect($browser->isSuccessful())->toBeTrue($browser->getErrorOutput().$browser->getOutput().$server->getErrorOutput());
        expect($item->fresh()->remainingAmount())->toBe('200.00')->and(Transaction::count())->toBe(0)->and(Transaction::withTrashed()->count())->toBe(1);
    } finally {
        $server?->stop();
        DB::disconnect('sqlite');
        @unlink($database);
        @unlink($database.'-wal');
        @unlink($database.'-shm');
    }
})->with(['desktop' => 1440, 'mobile' => 390]);
