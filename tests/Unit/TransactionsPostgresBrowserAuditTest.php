<?php

use App\Models\Category;
use App\Models\CounterpartyRelation;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\TransactionRefundService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('postgres transaction actions remain coherent after real browser requests', function () {
    if (! getenv('TRANSACTIONS_POSTGRES_BROWSER_AUDIT')) {
        $this->markTestSkipped('Opt in only with a disposable codex_transactions_audit_ PostgreSQL database.');
    }
    expect(DB::connection()->getDriverName())->toBe('pgsql');
    expect(config('database.connections.pgsql.database'))->toStartWith('codex_transactions_audit_');
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    config(['counterparty_ledger.public_enabled' => true]);
    $fixtures = [];
    foreach (['desktop', 'mobile'] as $device) {
        $user = User::factory()->create(['name' => 'PG Audit '.$device, 'email' => 'pg-audit-'.$device.'@example.test', 'locale' => 'it', 'base_currency_code' => 'EUR']);
        UserYear::create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
        $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
        $category = Category::create(['user_id' => $user->id, 'name' => 'PG Audit expense', 'slug' => 'pg-audit-expense', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
        $entry = RecurringEntry::create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id, 'title' => 'PG Audit automatic '.$device, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true, 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'start_date' => '2026-10-16', 'end_mode' => 'never', 'expected_amount' => 15, 'auto_create_transaction' => true]);
        $occurrence = $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-16', 'due_date' => '2026-10-16', 'expected_amount' => 15, 'status' => 'pending']);
        $automatic = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
        $original = userTransaction($user, $account, ['kind' => 'manual', 'category_id' => $category->id, 'amount' => 30, 'transaction_date' => '2026-10-16', 'description' => 'PG Audit refundable '.$device]);
        $refund = app(TransactionRefundService::class)->refund($original);
        $blockedEntry = $entry->replicate(['uuid']);
        $blockedEntry->title = 'PG Audit resettled '.$device;
        $blockedEntry->save();
        $blockedOccurrence = $blockedEntry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-16', 'due_date' => '2026-10-16', 'expected_amount' => 15, 'status' => 'pending']);
        $blockedOriginal = app(RecurringEntryPostingService::class)->post($blockedOccurrence);
        $blockedRefund = app(TransactionRefundService::class)->refund($blockedOriginal);
        userTransaction($user, $account, ['kind' => 'manual', 'category_id' => $category->id, 'amount' => 15, 'transaction_date' => '2026-10-16', 'recurring_entry_occurrence_id' => $blockedOccurrence->id, 'description' => 'PG Audit replacement '.$device]);
        $party = TrackedItem::create(['user_id' => $user->id, 'name' => 'PG Audit party '.$device, 'slug' => 'pg-audit-party-'.$device, 'is_active' => true, 'is_shared_expense_counterpart' => true]);
        userTransaction($user, $account, ['kind' => 'manual', 'category_id' => $category->id, 'amount' => 100, 'transaction_date' => '2026-10-16', 'description' => 'PG Audit shared '.$device, 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
        app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
        $relation = CounterpartyRelation::where('counterparty_id', $party->id)->firstOrFail();
        app(CounterpartyPaymentService::class)->activate($user, $relation, 'pg-audit-activate-'.$device);
        app(CounterpartyPaymentService::class)->create($user, $relation->fresh(), ['operation_key' => 'pg-audit-payment-'.$device, 'amount' => '50.00', 'date' => '2026-10-16', 'account_uuid' => $account->uuid, 'direction' => 'received', 'scope' => 'all', 'allow_unallocated' => false]);
        $rows = collect(app(MonthlyTransactionSheetService::class)->build($user, 2026, 10)['transactions']);
        expect($rows->firstWhere('uuid', $automatic->uuid)['can_confirm_payment'])->toBeTrue()
            ->and($rows->firstWhere('uuid', $refund->uuid)['can_undo_refund'])->toBeTrue()
            ->and($rows->firstWhere('uuid', $blockedRefund->uuid)['can_undo_refund'])->toBeFalse();
        $fixtures[$device] = ['user_id' => $user->id, 'email' => $user->email, 'automatic' => $automatic->uuid, 'refund' => $refund->uuid, 'original' => $original->uuid, 'blocked_refund' => $blockedRefund->uuid, 'relation' => $relation->uuid, 'account_id' => $account->id, 'balance_before' => (string) $account->fresh()->current_balance, 'count_before' => Transaction::where('user_id', $user->id)->count()];
    }
    file_put_contents('/tmp/transactions-pg-audit-manifest.json', json_encode($fixtures, JSON_THROW_ON_ERROR));
    $environment = ['APP_ENV' => 'local', 'APP_URL' => 'http://127.0.0.1:8194', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => config('database.connections.pgsql.database'), 'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'database', 'SESSION_DOMAIN' => '', 'SESSION_COOKIE' => 'codex_pg_audit_session', 'SESSION_SECURE_COOKIE' => 'false', 'APP_MAINTENANCE_DRIVER' => 'cache', 'APP_MAINTENANCE_STORE' => 'array', 'BROADCAST_CONNECTION' => 'log', 'QUEUE_CONNECTION' => 'sync', 'NIGHTWATCH_ENABLED' => 'false', 'COUNTERPARTY_LEDGER_PUBLIC_ENABLED' => 'true'];
    $router = '/tmp/transactions-pg-audit-router.php';
    file_put_contents($router, <<<'ROUTER'
<?php
$public = getcwd().'/public';
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
if ($path !== '/' && is_file($public.$path)) {
    return false;
}
define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
$app->make(Illuminate\Foundation\Vite::class)->useHotFile('/tmp/transactions-pg-audit-no-hot');
config(['recaptcha.enabled' => false]);
$app->handleRequest(Illuminate\Http\Request::capture());
ROUTER);
    $environment['APP_DEBUG'] = 'false';
    $environment['DEBUGBAR_ENABLED'] = 'false';
    $server = new Process([PHP_BINARY, '-S', '0.0.0.0:8194', '-t', 'public', $router], base_path(), $environment, timeout: 1800);
    $output = fopen('/tmp/transactions-pg-audit-http.log', 'w');
    $server->start(static function (string $type, string $buffer) use ($output): void {
        fwrite($output, $buffer);
    });
    try {
        $deadline = time() + 1700;
        while (! file_exists('/tmp/transactions-pg-audit-browser-done') && time() < $deadline && $server->isRunning()) {
            usleep(250000);
        }
        expect(file_exists('/tmp/transactions-pg-audit-browser-done'))->toBeTrue('Browser audit must finish before checking persisted state.');
        foreach ($fixtures as $fixture) {
            $automatic = Transaction::where('uuid', $fixture['automatic'])->firstOrFail();
            expect($automatic->payment_verified_at)->not->toBeNull()
                ->and(Transaction::where('uuid', $fixture['refund'])->exists())->toBeFalse()
                ->and(Transaction::where('uuid', $fixture['blocked_refund'])->exists())->toBeTrue()
                ->and(Transaction::where('user_id', $fixture['user_id'])->count())->toBe($fixture['count_before'] - 1)
                ->and((float) $automatic->account->current_balance)->toBe((float) $fixture['balance_before'] - 30);
            $rows = collect(app(MonthlyTransactionSheetService::class)->build(User::findOrFail($fixture['user_id']), 2026, 10)['transactions']);
            expect($rows->firstWhere('uuid', $fixture['automatic'])['can_confirm_payment'])->toBeFalse()
                ->and($rows->firstWhere('uuid', $fixture['original'])['can_refund'])->toBeTrue()
                ->and($rows->firstWhere('uuid', $fixture['blocked_refund'])['can_undo_refund'])->toBeFalse();
        }
    } finally {
        $server->stop();
        fclose($output);
        @unlink($router);
    }
});

test('postgres real HTTP rejects forbidden refund undo and cross account confirmation', function () {
    if (! getenv('TRANSACTIONS_POSTGRES_HTTP_PROBES')) {
        $this->markTestSkipped('Requires the running disposable PostgreSQL browser audit server.');
    }
    expect(DB::connection()->getDriverName())->toBe('pgsql');
    expect(config('database.connections.pgsql.database'))->toStartWith('codex_transactions_audit_');
    $fixtures = json_decode(file_get_contents('/tmp/transactions-pg-audit-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (['desktop', 'mobile'] as $device) {
        $cookies = new CookieJar;
        $client = Http::timeout(15)->withOptions(['cookies' => $cookies, 'allow_redirects' => false]);
        expect($client->get('http://127.0.0.1:8194/login')->status())->toBe(200);
        $xsrf = collect($cookies->toArray())->firstWhere('Name', 'XSRF-TOKEN')['Value'];
        $client->withHeaders(['X-XSRF-TOKEN' => urldecode($xsrf)]);
        expect($client->asForm()->post('http://127.0.0.1:8194/login', ['email' => $fixtures[$device]['email'], 'password' => 'password'])->status())->toBe(302);
        $xsrf = collect($cookies->toArray())->firstWhere('Name', 'XSRF-TOKEN')['Value'];
        $client = Http::timeout(15)->withOptions(['cookies' => $cookies, 'allow_redirects' => false])->withHeaders(['X-XSRF-TOKEN' => urldecode($xsrf)])->acceptJson();
        $refund = $fixtures[$device]['blocked_refund'];
        $response = $client->delete('http://127.0.0.1:8194/transactions/2026/10/'.$refund.'/refund');
        expect($response->status())->toBe(422)->and($response->json('errors.transaction'))->not->toBeNull();
        $other = $fixtures[$device === 'desktop' ? 'mobile' : 'desktop']['automatic'];
        expect($client->post('http://127.0.0.1:8194/transactions/'.$other.'/payment-confirmation')->status())->toBe(403);
        expect(Transaction::where('uuid', $refund)->exists())->toBeTrue();
    }
});
