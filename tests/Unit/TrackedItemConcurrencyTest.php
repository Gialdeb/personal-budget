<?php

use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('concurrent textual resolutions serialize on the same catalog without duplicate names', function (bool $shared) {
    $database = tempnam(sys_get_temp_dir(), 'soamco-reference-test-');
    $release = $database.'-release';
    $originalConnection = config('database.default');
    $originalSqlite = config('database.connections.sqlite');
    $workers = [];
    try {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true])->assertSuccessful();
        $user = User::factory()->create();
        $accountId = $shared ? createTestAccount($user)->id : null;
        $arguments = $user->id.', '.var_export($accountId, true);
        $bootstrap = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.connections.sqlite.busy_timeout" => 5000]); $resolver = app(App\Services\TrackedItems\TrackedItemResolver::class);';
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false'];
        $firstCode = $bootstrap.'$item = $resolver->inCatalog('.$arguments.', function () use ($resolver) { echo "locked\n"; flush(); $until = microtime(true) + 10; while (!file_exists('.var_export($release, true).')) { if (microtime(true) > $until) { throw new RuntimeException("Barrier timeout"); } usleep(10000); } return $resolver->resolve('.$arguments.', "Caffè Roma", counterparty: '.($shared ? 'false' : 'true').'); }); echo $item->uuid;';
        $first = new Process([PHP_BINARY, '-r', $firstCode], base_path(), $env, timeout: 20);
        $workers[] = $first;
        $first->start();
        expect($first->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'locked')))->toBeTrue($first->getErrorOutput());
        $second = new Process([PHP_BINARY, '-r', $bootstrap.'echo "resolving\n"; flush(); echo $resolver->resolve('.$arguments.', "CAFFE  ROMA")->uuid;'], base_path(), $env, timeout: 20);
        $workers[] = $second;
        $second->start();
        expect($second->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'resolving')))->toBeTrue($second->getErrorOutput());
        usleep(200000);
        expect($second->isRunning())->toBeTrue('The competing resolver must wait for the held catalog lock.');
        touch($release);
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        }
        $reference = TrackedItem::sole();
        expect(trim(str_replace("locked\n", '', $first->getOutput())))->toBe($reference->uuid)
            ->and(trim(str_replace("resolving\n", '', $second->getOutput())))->toBe($reference->uuid)
            ->and($reference->account_id)->toBe($accountId);
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
})->with(['personal' => false, 'shared account' => true]);
