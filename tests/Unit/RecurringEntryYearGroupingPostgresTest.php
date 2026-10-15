<?php

use App\Models\RecurringEntry;
use App\Services\Recurring\RecurringEntryDetailReadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

test('groups native PostgreSQL dates by effective year without hydrating the history', function () {
    if (! getenv('RECURRING_HISTORY_PG_PORT')) {
        $this->markTestSkipped('Opt in with RECURRING_HISTORY_PG_PORT pointing to an isolated PostgreSQL instance.');
    }

    $connectionName = 'recurring_history_pg_test';
    config(['database.connections.'.$connectionName => [
        'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => getenv('RECURRING_HISTORY_PG_PORT'),
        'database' => 'postgres', 'username' => 'codex_regression', 'password' => '', 'charset' => 'utf8', 'prefix' => '',
    ]]);
    $connection = DB::connection($connectionName);

    try {
        $connection->statement('CREATE TEMPORARY TABLE recurring_entry_occurrences (recurring_entry_id BIGINT NOT NULL, expected_date DATE NOT NULL, due_date DATE NULL)');
        $connection->table('recurring_entry_occurrences')->insert([
            ['recurring_entry_id' => 34, 'expected_date' => '2024-01-01', 'due_date' => null],
            ['recurring_entry_id' => 34, 'expected_date' => '2024-12-31', 'due_date' => '2025-01-01'],
            ['recurring_entry_id' => 34, 'expected_date' => '2025-06-01', 'due_date' => null],
            ['recurring_entry_id' => 34, 'expected_date' => '2026-10-01', 'due_date' => null],
            ['recurring_entry_id' => 35, 'expected_date' => '2023-01-01', 'due_date' => null],
        ]);
        $entry = (new RecurringEntry)->setConnection($connectionName);
        $entry->forceFill(['id' => 34]);
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        expect(app(RecurringEntryDetailReadService::class)->years($entry))->toBe([
            ['year' => 2024, 'count' => 1],
            ['year' => 2025, 'count' => 2],
            ['year' => 2026, 'count' => 1],
        ]);
        expect($connection->getQueryLog())->toHaveCount(1);
        expect($entry->relationLoaded('occurrences'))->toBeFalse();
    } finally {
        $connection->disableQueryLog();
        DB::disconnect($connectionName);
        DB::purge($connectionName);
    }
});
