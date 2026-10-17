<?php

use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\User;
use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('concurrent identity registrations serialize idempotency conflicts and inverse edges', function (string $scenario) {
    $database = tempnam(sys_get_temp_dir(), 'soamco-identity-test-');
    $release = $database.'-release';
    $originalConnection = config('database.default');
    $originalSqlite = config('database.connections.sqlite');
    $workers = [];
    try {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true])->assertSuccessful();
        $actor = User::factory()->create();
        $references = collect(range(1, 3))->map(fn (): TrackedItem => TrackedItem::create([
            'user_id' => $actor->id, 'name' => 'Reference '.Str::uuid(), 'slug' => (string) Str::uuid(), 'is_active' => true,
        ]));
        [$source, $target, $third] = $references->all();
        $bootstrap = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.connections.sqlite.busy_timeout" => 5000]); $actor = App\Models\User::findOrFail('.$actor->id.'); $identity = app(App\Services\TrackedItems\TrackedItemIdentityService::class);';
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false'];
        $firstCode = $bootstrap.'$alias = app(App\Services\TrackedItems\TrackedItemResolver::class)->inCatalog($actor->id, null, function () use ($actor, $identity) { echo "locked\n"; flush(); $until = microtime(true) + 10; while (!file_exists('.var_export($release, true).')) { if (microtime(true) > $until) { throw new RuntimeException("Barrier timeout"); } usleep(10000); } return $identity->register($actor, App\Models\TrackedItem::findOrFail('.$source->id.'), App\Models\TrackedItem::findOrFail('.$target->id.')); }); echo json_encode(["uuid" => $alias->uuid]);';
        $first = new Process([PHP_BINARY, '-r', $firstCode], base_path(), $env, timeout: 20);
        $workers[] = $first;
        $first->start();
        expect($first->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'locked')))->toBeTrue($first->getErrorOutput());
        $secondSource = $scenario === 'inverse edges' ? $target : $source;
        $secondTarget = match ($scenario) {
            'same identity' => $target,
            'different destinations' => $third,
            'inverse edges' => $source,
        };
        $secondCode = $bootstrap.'echo "attempting\n"; flush(); try { $alias = $identity->register($actor, App\Models\TrackedItem::findOrFail('.$secondSource->id.'), App\Models\TrackedItem::findOrFail('.$secondTarget->id.')); echo json_encode(["uuid" => $alias->uuid]); } catch (Illuminate\Validation\ValidationException $exception) { echo json_encode(["rejected" => true]); }';
        $second = new Process([PHP_BINARY, '-r', $secondCode], base_path(), $env, timeout: 20);
        $workers[] = $second;
        $second->start();
        expect($second->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'attempting')))->toBeTrue($second->getErrorOutput());
        usleep(200000);
        expect($second->isRunning())->toBeTrue('The competing identity registration must wait for the catalog lock.');
        touch($release);
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        }
        $firstResult = json_decode(str_replace("locked\n", '', $first->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $secondResult = json_decode(str_replace("attempting\n", '', $second->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        expect(TrackedItemAlias::count())->toBe(1)
            ->and($source->fresh()->is_active)->toBeFalse()
            ->and($target->fresh()->is_active)->toBeTrue()
            ->and($third->fresh()->is_active)->toBeTrue()
            ->and(app(TrackedItemIdentityService::class)->canonicalFor($source)->id)->toBe($target->id);
        if ($scenario === 'same identity') {
            expect($secondResult['uuid'])->toBe($firstResult['uuid']);
        } else {
            expect($secondResult['rejected'])->toBeTrue();
        }
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
})->with(['same identity', 'different destinations', 'inverse edges']);
