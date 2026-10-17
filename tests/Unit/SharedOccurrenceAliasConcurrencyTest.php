<?php

use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('alias registration waits until an occurrence posting commits its original economic identity', function () {
    $database = tempnam(sys_get_temp_dir(), 'soamco-occurrence-test-');
    $release = $database.'-release';
    $originalConnection = config('database.default');
    $originalSqlite = config('database.connections.sqlite');
    $workers = [];
    try {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true])->assertSuccessful();
        $actor = User::factory()->create(['base_currency_code' => 'EUR']);
        $account = createTestAccount($actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
        $source = TrackedItem::create(['user_id' => $actor->id, 'name' => 'Origine', 'slug' => 'source', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
        $target = TrackedItem::create(['user_id' => $actor->id, 'name' => 'Canonico', 'slug' => 'target', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
        $entry = RecurringEntry::create(['user_id' => $actor->id, 'account_id' => $account->id, 'title' => 'Scadenza', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'expected_amount' => 100, 'shared_expense_counterparty_id' => $source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
        $occurrence = $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-01', 'expected_amount' => 100, 'status' => 'pending']);
        $bootstrap = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.connections.sqlite.busy_timeout" => 5000, "counterparty_ledger.shadow_shared_enabled" => false]);';
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false'];
        $firstCode = $bootstrap.'Illuminate\Support\Facades\DB::transaction(function () { app(App\Services\Recurring\RecurringEntryPostingService::class)->post(App\Models\RecurringEntryOccurrence::findOrFail('.$occurrence->id.')); echo "posted\n"; flush(); $until = microtime(true) + 10; while (!file_exists('.var_export($release, true).')) { if (microtime(true) > $until) { throw new RuntimeException("Barrier timeout"); } usleep(10000); } });';
        $first = new Process([PHP_BINARY, '-r', $firstCode], base_path(), $env, timeout: 20);
        $workers[] = $first;
        $first->start();
        expect($first->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'posted')))->toBeTrue($first->getErrorOutput());
        $secondCode = $bootstrap.'echo "attempting\n"; flush(); app(App\Services\TrackedItems\TrackedItemIdentityService::class)->register(App\Models\User::findOrFail('.$actor->id.'), App\Models\TrackedItem::findOrFail('.$source->id.'), App\Models\TrackedItem::findOrFail('.$target->id.'));';
        $second = new Process([PHP_BINARY, '-r', $secondCode], base_path(), $env, timeout: 20);
        $workers[] = $second;
        $second->start();
        expect($second->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'attempting')))->toBeTrue($second->getErrorOutput());
        usleep(200000);
        expect($second->isRunning())->toBeTrue('The alias must wait for the posting transaction.');
        touch($release);
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        }
        expect(Transaction::sole()->shared_expense_counterparty_id)->toBe($source->id)
            ->and(TrackedItemAlias::sole()->canonical_tracked_item_id)->toBe($target->id)
            ->and($source->fresh()->is_active)->toBeFalse();
    } finally {
        foreach ($workers as $worker) {
            $worker->stop();
        }
        DB::purge('sqlite');
        config(['database.default' => $originalConnection, 'database.connections.sqlite' => $originalSqlite]);
        foreach ([$database, $release, $database.'-wal', $database.'-shm', $database.'-journal'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});
