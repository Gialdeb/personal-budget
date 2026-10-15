<?php

use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('recurring conversion focuses its actual transaction repeatedly on desktop mobile and standalone', function (int $width, bool $standalone) {
    if (! getenv('RECURRING_BROWSER_MODULE_PATH')) {
        $this->markTestSkipped('Opt in with an existing Playwright module path and built frontend assets.');
    }
    $database = tempnam(sys_get_temp_dir(), 'recurring-focus-browser-');
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null]);
    DB::purge('sqlite');
    $server = null;
    try {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $user = User::factory()->create(['email' => 'recurring-focus@example.test', 'locale' => 'it', 'base_currency_code' => 'EUR']);
        UserYear::query()->create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
        $account = createTestAccount($user, ['opening_balance' => 10000, 'current_balance' => 10000, 'opening_balance_date' => '2026-01-01']);
        $category = Category::query()->create(['user_id' => $user->id, 'name' => 'Focus expense', 'slug' => 'focus-expense', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
        $fixtures = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $i % 2 === 0 ? '2026-10-01' : '2026-09-01';
            $entry = RecurringEntry::query()->create([
                'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id, 'title' => 'Focus conversion '.$i,
                'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => $i < 3 ? 'recurring' : 'installment',
                'status' => 'active', 'is_active' => true, 'start_date' => $date,
                'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'after_occurrences',
                'occurrences_limit' => 1, 'installments_count' => 1, 'expected_amount' => 50, 'total_amount' => 50,
                'is_amount_variable' => false, 'auto_create_transaction' => false,
            ]);
            $occurrence = $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => $date, 'due_date' => $date, 'expected_amount' => 50, 'status' => 'pending']);
            if ($i === 6) {
                $occurrence->update(['expected_amount' => 100]);
                $entry->update(['expected_amount' => 100, 'total_amount' => 100]);
                $advance = Transaction::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id, 'direction' => 'expense', 'amount' => 50, 'currency' => 'EUR', 'transaction_date' => '2026-10-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'description' => 'Advance payment']);
                app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $advance, $user, '50.00');
            }
            $fixtures[] = ['entry' => $entry->uuid, 'occurrence' => $occurrence->uuid, 'title' => $entry->title, 'month' => $i % 2 === 0 ? 10 : 9, 'payment' => $i === 6];
        }
        foreach ([9, 10] as $month) {
            for ($i = 0; $i < 70; $i++) {
                Transaction::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'direction' => 'expense', 'amount' => 1, 'currency' => 'EUR', 'transaction_date' => '2026-'.$month.'-10', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'description' => 'Long list '.$i]);
            }
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $url = 'http://127.0.0.1:'.$port;
        $environment = ['APP_MAINTENANCE_DRIVER' => 'cache', 'APP_MAINTENANCE_STORE' => 'array', 'APP_ENV' => 'testing', 'APP_URL' => $url, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'database', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false', 'BROADCAST_CONNECTION' => 'log', 'QUEUE_CONNECTION' => 'sync', 'NIGHTWATCH_ENABLED' => 'false', 'RECAPTCHA_V3_ENABLED' => 'false'];
        $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload'], base_path(), $environment, timeout: 240);
        $server->disableOutput();
        $server->start();
        $browser = new Process(['node', base_path('tests/frontend/recurring-focus-browser.mjs')], base_path(), ['RECURRING_BROWSER_URL' => $url, 'RECURRING_BROWSER_FIXTURES' => json_encode($fixtures, JSON_THROW_ON_ERROR), 'RECURRING_BROWSER_WIDTH' => (string) $width, 'RECURRING_BROWSER_STANDALONE' => $standalone ? 'true' : 'false'], timeout: 200);
        $browser->run();
        expect($browser->isSuccessful())->toBeTrue($browser->getErrorOutput().$browser->getOutput());
        expect(Transaction::count())->toBe(148);
        fwrite(STDOUT, $browser->getOutput());
    } finally {
        $server?->stop();
        DB::disconnect('sqlite');
        @unlink($database);
        @unlink($database.'-wal');
        @unlink($database.'-shm');
    }
})->with(['desktop' => [1440, false], 'mobile' => [390, false], 'standalone reduced motion' => [390, true]]);
