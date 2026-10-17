<?php

use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\User;
use App\Services\TrackedItems\MoveTrackedItemMovementsService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('movement and restore revalidate after waiting for a concurrent writer', function (string $operation) {
    $database = tempnam(sys_get_temp_dir(), 'soamco-move-test-');
    $release = $database.'-release';
    $originalConnection = config('database.default');
    $originalSqlite = config('database.connections.sqlite');
    $workers = [];
    try {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true])->assertSuccessful();
        $actor = User::factory()->create();
        $account = createTestAccount($actor);
        $source = TrackedItem::create(['user_id' => $actor->id, 'name' => 'Origine', 'slug' => 'source', 'is_active' => true]);
        $target = TrackedItem::create(['user_id' => $actor->id, 'name' => 'Canonico', 'slug' => 'target', 'is_active' => true]);
        $transaction = userTransaction($actor, $account, ['tracked_item_id' => $source->id, 'amount' => 10]);
        $preview = app(MoveTrackedItemMovementsService::class)->preview($actor, $source->uuid, $target->uuid);
        $moveUuid = null;
        if ($operation === 'restore') {
            app(MoveTrackedItemMovementsService::class)->confirm($actor, $source->uuid, $target->uuid, $preview['preview_token']);
            $moveUuid = DB::table('tracked_item_transaction_moves')->value('uuid');
        }
        $bootstrap = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.connections.sqlite.busy_timeout" => 5000]);';
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false'];
        $firstCode = $bootstrap.'app(App\Services\TrackedItems\TrackedItemResolver::class)->inCatalog('.$actor->id.', null, function () { Illuminate\Support\Facades\DB::table("transactions")->where("id", '.$transaction->id.')->update(["amount" => 99]); echo "changed\n"; flush(); $until = microtime(true) + 10; while (!file_exists('.var_export($release, true).')) { if (microtime(true) > $until) { throw new RuntimeException("Barrier timeout"); } usleep(10000); } });';
        $first = new Process([PHP_BINARY, '-r', $firstCode], base_path(), $env, timeout: 20);
        $workers[] = $first;
        $first->start();
        expect($first->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'changed')))->toBeTrue($first->getErrorOutput());
        $secondCode = $bootstrap.'echo "attempting\n"; flush(); try { app(App\Services\TrackedItems\MoveTrackedItemMovementsService::class)->confirm(App\Models\User::findOrFail('.$actor->id.'), '.var_export($source->uuid, true).', '.var_export($target->uuid, true).', '.var_export($preview['preview_token'], true).'); echo "unexpected"; } catch (Illuminate\Validation\ValidationException $exception) { echo "stale"; }';
        if ($operation === 'restore') {
            $secondCode = $bootstrap.'echo "attempting\n"; flush(); try { app(App\Services\TrackedItems\MoveTrackedItemMovementsService::class)->restore(App\Models\User::findOrFail('.$actor->id.'), '.var_export($moveUuid, true).'); echo "unexpected"; } catch (Illuminate\Validation\ValidationException $exception) { echo "stale"; }';
        }
        $second = new Process([PHP_BINARY, '-r', $secondCode], base_path(), $env, timeout: 20);
        $workers[] = $second;
        $second->start();
        expect($second->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'attempting')))->toBeTrue($second->getErrorOutput());
        usleep(200000);
        expect($second->isRunning())->toBeTrue('Confirmation must wait for the catalog lock.');
        touch($release);
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        }
        expect($second->getOutput())->toContain('stale')->not->toContain('unexpected');
        expect($transaction->fresh()->tracked_item_id)->toBe($operation === 'restore' ? $target->id : $source->id);
        expect(DB::table('tracked_item_transaction_moves')->whereNotNull('restored_at')->count())->toBe(0);
        expect(TrackedItemAlias::count())->toBe(0)->and($source->fresh()->is_active)->toBeTrue();
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
})->with(['move', 'restore']);
