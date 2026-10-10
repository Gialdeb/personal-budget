<?php

use App\Models\AutomationRun;
use App\Models\CreditDebtItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Maintenance\TechnicalArchiveService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function oldTechnicalRun(string $key = 'test-pipeline', int $days = 120, string $status = 'success'): AutomationRun
{
    return AutomationRun::query()->forceCreate([
        'automation_key' => $key, 'pipeline' => $key, 'status' => $status, 'trigger_type' => 'scheduled',
        'started_at' => now()->subDays($days)->subMinute(), 'finished_at' => now()->subDays($days),
        'context' => ['owner_id' => 123, 'nested' => ['italian' => 'Città', 'null' => null]],
        'result' => ['count' => 3], 'created_at' => now()->subDays($days), 'updated_at' => now()->subDays($days),
    ]);
}

test('old completed technical records are archived in full and recent and latest records stay online', function (string $status) {
    $old = oldTechnicalRun(status: $status);
    $raw = (array) DB::table('automation_runs')->find($old->id);
    $latest = oldTechnicalRun(days: 1);
    $onlyOld = oldTechnicalRun('old-only-pipeline');
    $stats = app(TechnicalArchiveService::class)->archive('automation_runs', 90, 100, 5000);
    expect($stats['archived'])->toBe(1)->and($stats['deleted'])->toBe(1)
        ->and(AutomationRun::find($old->id))->toBeNull();
    $this->assertModelExists($latest);
    $this->assertModelExists($onlyOld);
    expect(app(TechnicalArchiveService::class)->retrieve('automation_runs', (string) $old->id))->toEqual($raw);
    $archive = DB::table('technical_archives')->first();
    expect($archive->payload_codec)->toBe('gzip-base64-json')->and((int) $archive->payload_version)->toBe(1)
        ->and($archive->original_created_at)->toBe($raw['created_at']);
})->with(['success', 'skipped']);

test('pending running and retryable terminal runs remain online regardless of age', function (string $status) {
    $old = oldTechnicalRun(status: $status);
    oldTechnicalRun(days: 1);
    $stats = app(TechnicalArchiveService::class)->archive('automation_runs', 90, 10, 100);
    expect($stats['deleted'])->toBe(0);
    $this->assertModelExists($old);
})->with(['pending', 'running', 'failed', 'warning', 'timed_out']);

test('archive insertion failure rolls back and leaves all original records intact', function () {
    $old = oldTechnicalRun();
    oldTechnicalRun(days: 1);
    DB::statement("CREATE TRIGGER reject_technical_archive BEFORE INSERT ON technical_archives BEGIN SELECT RAISE(ABORT, 'archive unavailable'); END");
    try {
        expect(fn () => app(TechnicalArchiveService::class)->archive('automation_runs', 90, 10, 100))->toThrow(QueryException::class);
    } finally {
        DB::statement('DROP TRIGGER reject_technical_archive');
    }
    $this->assertModelExists($old);
    expect(DB::table('technical_archives')->count())->toBe(0);
});

test('verification failure rolls back the entire batch including earlier records', function () {
    $first = oldTechnicalRun();
    $second = oldTechnicalRun();
    oldTechnicalRun(days: 1);
    $archives = new class extends TechnicalArchiveService
    {
        private int $reads = 0;

        public function retrieve(string $table, string $sourceKey): ?array
        {
            if (++$this->reads === 2) {
                throw new RuntimeException('verification unavailable');
            }

            return parent::retrieve($table, $sourceKey);
        }
    };
    expect(fn () => $archives->archive('automation_runs', 90, 10, 100))->toThrow(RuntimeException::class);
    $this->assertModelExists($first);
    $this->assertModelExists($second);
    expect(DB::table('technical_archives')->count())->toBe(0);
});

test('retry and an interrupted run resume across multiple bounded chunks without duplicates', function () {
    foreach (range(1, 5) as $index) {
        oldTechnicalRun();
    }
    oldTechnicalRun(days: 1);
    $archives = app(TechnicalArchiveService::class);
    expect($archives->archive('automation_runs', 90, 2, 3)['deleted'])->toBe(3);
    expect($archives->archive('automation_runs', 90, 2, 3)['deleted'])->toBe(2);
    expect($archives->archive('automation_runs', 90, 2, 3)['deleted'])->toBe(0);
    expect(DB::table('technical_archives')->count())->toBe(5)->and(AutomationRun::count())->toBe(1);
});

test('an existing verified archive is reused but a changed or corrupted archive never permits deletion', function (bool $corrupt) {
    $old = oldTechnicalRun();
    $raw = (array) DB::table('automation_runs')->find($old->id);
    oldTechnicalRun(days: 1);
    $archives = app(TechnicalArchiveService::class);
    $archives->archive('automation_runs', 90, 10, 100);
    DB::table('automation_runs')->insert($raw);
    if ($corrupt) {
        DB::table('technical_archives')->update(['checksum' => str_repeat('0', 64)]);
        expect(fn () => $archives->archive('automation_runs', 90, 10, 100))->toThrow(RuntimeException::class);
        $this->assertModelExists($old);
    } else {
        $stats = $archives->archive('automation_runs', 90, 10, 100);
        expect($stats['archived'])->toBe(0)->and($stats['deleted'])->toBe(1);
    }
    expect(DB::table('technical_archives')->count())->toBe(1);
})->with([false, true]);

test('dry run reports bounded candidates without modifying source or archive', function () {
    oldTechnicalRun();
    oldTechnicalRun();
    oldTechnicalRun(days: 1);
    $stats = app(TechnicalArchiveService::class)->archive('automation_runs', 90, 1, 1, true);
    expect($stats['analyzed'])->toBe(1)->and($stats['would_delete'])->toBe(1)
        ->and($stats['archived'])->toBe(0)->and($stats['deleted'])->toBe(0);
    $this->artisan('maintenance:archive-old-data --dry-run --batch-size=1 --limit=1')->assertSuccessful();
    expect(DB::table('technical_archives')->count())->toBe(0)->and(AutomationRun::count())->toBe(3);
});

test('all non allowlisted tables including every financial table are rejected even via configuration', function () {
    $archives = app(TechnicalArchiveService::class);
    $user = User::factory()->create();
    $account = createTestAccount($user, ['current_balance' => '1000.00']);
    $manual = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '500.00']);
    $transaction = Transaction::create([
        'user_id' => $user->id, 'account_id' => $account->id, 'amount' => '450.00',
        'direction' => 'income', 'currency' => 'EUR', 'kind' => 'manual',
        'source_type' => 'manual', 'status' => 'confirmed', 'transaction_date' => now()->subYears(5),
    ]);
    $originalAccount = $account->fresh()->getAttributes();
    $originalTransaction = $transaction->fresh()->getAttributes();
    foreach (Schema::getTableListing() as $table) {
        if ($table === 'automation_runs') {
            continue;
        }
        expect(fn () => $archives->archive($table, 1, 1, 1))->toThrow(InvalidArgumentException::class);
        expect(fn () => $archives->retrieve($table, '1'))->toThrow(InvalidArgumentException::class);
    }
    config(['technical-archive.retention_days.transactions' => 1]);
    oldTechnicalRun();
    oldTechnicalRun(days: 1);
    $financialTables = ['transactions', 'accounts', 'recurring_entries', 'credit_debt_items', 'shared_expense_ledger_entries'];
    $before = collect($financialTables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);
    $this->artisan('maintenance:archive-old-data')->assertSuccessful();
    foreach ($before as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(DB::table('technical_archives')->pluck('source_table')->unique()->all())->toBe(['automation_runs']);
    expect($account->fresh()->getAttributes())->toBe($originalAccount)
        ->and($transaction->fresh()->getAttributes())->toBe($originalTransaction)
        ->and($manual->fresh()->total_amount)->toBe('500.00');
});

test('interruption retains committed chunks and a subsequent run safely completes remaining chunks', function () {
    foreach (range(1, 5) as $index) {
        oldTechnicalRun();
    }
    oldTechnicalRun(days: 1);
    $interrupted = new class extends TechnicalArchiveService
    {
        private int $reads = 0;

        public function retrieve(string $table, string $sourceKey): ?array
        {
            if (++$this->reads === 3) {
                throw new RuntimeException('interrupted');
            }

            return parent::retrieve($table, $sourceKey);
        }
    };
    expect(fn () => $interrupted->archive('automation_runs', 90, 2, 100))->toThrow(RuntimeException::class);
    expect(DB::table('technical_archives')->count())->toBe(2)->and(AutomationRun::count())->toBe(4);
    expect(app(TechnicalArchiveService::class)->archive('automation_runs', 90, 2, 100)['deleted'])->toBe(3);
    expect(DB::table('technical_archives')->count())->toBe(5)->and(AutomationRun::count())->toBe(1);
});

test('retention is configurable and a recently finished or updated run is never archived', function () {
    $old = oldTechnicalRun(days: 95);
    $recentFinish = oldTechnicalRun();
    DB::table('automation_runs')->where('id', $recentFinish->id)->update(['finished_at' => now()]);
    $recentUpdate = oldTechnicalRun();
    DB::table('automation_runs')->where('id', $recentUpdate->id)->update(['updated_at' => now()]);
    oldTechnicalRun(days: 1);
    config(['technical-archive.retention_days.automation_runs' => 100]);
    $this->artisan('maintenance:archive-old-data')->assertSuccessful();
    expect(DB::table('technical_archives')->count())->toBe(0);
    config(['technical-archive.retention_days.automation_runs' => 90]);
    $this->artisan('maintenance:archive-old-data')->assertSuccessful();
    expect(DB::table('technical_archives')->count())->toBe(1)->and(AutomationRun::find($old->id))->toBeNull();
    $this->assertModelExists($recentFinish);
    $this->assertModelExists($recentUpdate);
});

test('technical maintenance is scheduled daily with overlapping and multi server protection', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'technical-archive-maintenance');
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 5 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

test('archives retain separate owner contexts and export verified payload without replaying the run', function () {
    $first = oldTechnicalRun('first');
    $second = oldTechnicalRun('second');
    DB::table('automation_runs')->where('id', $second->id)->update(['context' => json_encode(['owner_id' => 456])]);
    oldTechnicalRun('first', 1);
    oldTechnicalRun('second', 1);
    $archives = app(TechnicalArchiveService::class);
    $archives->archive('automation_runs', 90, 1, 100);
    expect($archives->retrieve('automation_runs', (string) $first->id)['context'])->toBe(json_encode(['owner_id' => 123, 'nested' => ['italian' => 'Città', 'null' => null]]))
        ->and($archives->retrieve('automation_runs', (string) $second->id)['context'])->toBe(json_encode(['owner_id' => 456]));
    $this->artisan('maintenance:export-archive', ['source_table' => 'automation_runs', 'source_key' => $first->id])->assertSuccessful();
    $this->artisan('maintenance:export-archive', ['source_table' => 'transactions', 'source_key' => '1'])->assertFailed();
    $this->artisan('maintenance:export-archive', ['source_table' => 'automation_runs', 'source_key' => '999999'])->assertFailed();
    expect(AutomationRun::count())->toBe(2);
});

test('migration rollback cannot destroy the sole archived copy', function () {
    $run = oldTechnicalRun();
    oldTechnicalRun(days: 1);
    $archives = app(TechnicalArchiveService::class);
    $archives->archive('automation_runs', 90, 100, 100);
    $migration = require database_path('migrations/2026_10_10_080907_create_technical_archives_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($archives->retrieve('automation_runs', (string) $run->id)['id'])->toBe($run->id)
        ->and(DB::table('technical_archives')->count())->toBe(1);
});

test('invalid configuration and arguments fail closed', function () {
    oldTechnicalRun();
    oldTechnicalRun(days: 1);
    foreach (['--batch-size=0', '--batch-size=1001', '--batch-size=no', '--limit=0'] as $option) {
        $this->artisan('maintenance:archive-old-data '.$option)->assertFailed();
    }
    config(['technical-archive.retention_days.automation_runs' => 0]);
    $this->artisan('maintenance:archive-old-data')->assertFailed();
    expect(AutomationRun::count())->toBe(2)->and(DB::table('technical_archives')->count())->toBe(0);
});
