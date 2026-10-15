<?php

use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('economic identity and shared UX work in desktop mobile and standalone browser', function (int $width, bool $standalone) {
    if (! getenv('ECONOMIC_BROWSER_MODULE_PATH')) {
        $this->markTestSkipped('Opt in with a Playwright module path and built assets.');
    }
    $database = tempnam(sys_get_temp_dir(), 'economic-browser-');
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'features.credits_debts.enabled' => true]);
    DB::purge('sqlite');
    $server = null;
    try {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $fixtures = [];
        foreach (['A', 'B', 'C', 'D'] as $case) {
            $user = User::factory()->create(['email' => 'economic-'.strtolower($case).'@example.test', 'locale' => 'it', 'base_currency_code' => 'EUR']);
            $account = createTestAccount($user, ['name' => 'Conto verifica', 'opening_balance' => 1000, 'current_balance' => 1000]);
            Category::create(['user_id' => $user->id, 'name' => 'Entrate verifica', 'slug' => 'entrate-verifica', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);
            $mario = TrackedItem::create(['user_id' => $user->id, 'name' => 'Mario Rossi', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
            $pino = TrackedItem::create(['user_id' => $user->id, 'name' => 'Pino Mauro', 'slug' => 'pino', 'is_active' => true, 'settings' => ['economic_subject' => true]]);
            if ($case !== 'D') {
                userTransaction($user, $account, ['description' => 'Pulizie casa', 'amount' => $case === 'C' ? 40 : 122, 'shared_expense_counterparty_id' => $mario->id, 'shared_expense_paid_by' => $case === 'C' ? 'counterparty' : 'self', 'shared_expense_my_share_cents' => $case === 'C' ? 2000 : 6100, 'shared_expense_counterparty_share_cents' => $case === 'C' ? 2000 : 6100]);
                CreditDebtItem::factory()->forAccount($account)->create(['description' => 'Posizione manuale', 'reference_id' => $case === 'A' ? $pino->id : $mario->id, 'type' => $case === 'C' ? 'debit' : 'credit', 'total_amount' => $case === 'C' ? 100 : 280]);
            } else {
                foreach ([['Parziale', 100, 40], ['Regolata', 16, 16]] as [$name, $total, $refunded]) {
                    $source = userTransaction($user, $account, ['description' => $name, 'amount' => $total, 'shared_expense_counterparty_id' => $mario->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => $total * 50, 'shared_expense_counterparty_share_cents' => $total * 50]);
                    userTransaction($user, $account, ['direction' => 'income', 'amount' => $refunded, 'refunded_transaction_id' => $source->id]);
                }
            }
            if ($case === 'A') {
                legacyCreditDebtItem($user, ['description' => 'Riferimento da completare']);
            }
            $fixtures[$case] = ['email' => $user->email, 'mario' => $mario->uuid, 'pino' => $pino->uuid];
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $url = 'http://127.0.0.1:'.$port;
        $env = ['APP_ENV' => 'testing', 'APP_URL' => $url, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'database', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false', 'BROADCAST_CONNECTION' => 'log', 'QUEUE_CONNECTION' => 'sync', 'NIGHTWATCH_ENABLED' => 'false', 'FEATURE_CREDITS_DEBTS_ENABLED' => 'true', 'COUNTERPARTY_LEDGER_PUBLIC_ENABLED' => 'false', 'RECAPTCHA_V3_ENABLED' => 'false', 'APP_MAINTENANCE_DRIVER' => 'cache', 'APP_MAINTENANCE_STORE' => 'array'];
        $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload'], base_path(), $env, timeout: 600);
        $server->disableOutput();
        $server->start();
        $browser = new Process(['node', base_path('tests/frontend/economic-identity-browser.mjs')], base_path(), ['ECONOMIC_BROWSER_URL' => $url, 'ECONOMIC_BROWSER_FIXTURES' => json_encode($fixtures, JSON_THROW_ON_ERROR), 'ECONOMIC_BROWSER_WIDTH' => (string) $width, 'ECONOMIC_BROWSER_STANDALONE' => $standalone ? 'true' : 'false'], timeout: 180);
        $browser->run();
        expect($browser->isSuccessful())->toBeTrue($browser->getErrorOutput().$browser->getOutput());
        fwrite(STDOUT, $browser->getOutput());
        if (getenv('ECONOMIC_BROWSER_INTERACTIVE') && $width === 1440) {
            $manifest = getenv('ECONOMIC_BROWSER_SESSION') ?: sys_get_temp_dir().'/economic-browser-session.json';
            file_put_contents($manifest, json_encode(['url' => $url, 'fixtures' => $fixtures], JSON_THROW_ON_ERROR));
            $deadline = microtime(true) + 600;
            while (is_file($manifest) && microtime(true) < $deadline) {
                usleep(250000);
            }
            @unlink($manifest);
        }
    } finally {
        $server?->stop();
        DB::disconnect('sqlite');
        @unlink($database);
        @unlink($database.'-wal');
        @unlink($database.'-shm');
    }
})->with(['desktop' => [1440, false], 'mobile' => [390, false], 'standalone' => [390, true]]);
