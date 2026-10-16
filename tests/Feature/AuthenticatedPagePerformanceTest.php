<?php

use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Counterparties\CounterpartyViewService;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\MonthlyFinancialRecapService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Reports\AccountVisionReportService;
use App\Services\Reports\CategoryBreakdownReportService;
use App\Services\Reports\PeriodOverviewReportService;
use App\Support\Pwa\PwaManifestData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Inertia\ResponseFactory;

test('authenticated page matrix preserves successful responses and records query costs', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
    config(['features.credits_debts.enabled' => true, 'counterparty_ledger.public_enabled' => true]);
    $user = User::factory()->create(['name' => 'Performance', 'surname' => 'Fixture', 'email' => 'performance@example.test']);
    UserYear::firstOrCreate(['user_id' => $user->id, 'year' => 2026]);
    $account = userAccount($user, ['name' => 'Primary account']);
    $parties = collect(range(1, 8))->map(fn ($i) => TrackedItem::create([
        'user_id' => $user->id, 'name' => 'Counterparty '.$i, 'slug' => 'counterparty-'.$i,
        'type' => 'person', 'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]));
    foreach (range(1, 120) as $i) {
        userTransaction($user, $account, [
            'transaction_date' => '2026-10-'.str_pad((string) (($i % 13) + 1), 2, '0', STR_PAD_LEFT),
            'description' => 'Movement '.$i,
            'shared_expense_counterparty_id' => $i <= 40 ? $parties[($i - 1) % 8]->id : null,
            'shared_expense_paid_by' => $i <= 40 ? 'self' : null,
            'shared_expense_my_share_cents' => $i <= 40 ? 5000 : null,
            'shared_expense_counterparty_share_cents' => $i <= 40 ? 5000 : null,
        ]);
    }
    foreach (range(1, 24) as $i) {
        CreditDebtItem::factory()->forAccount($account)->create(['counterparty_id' => $parties[($i - 1) % 8]->id, 'description' => 'Manual position '.$i]);
    }
    foreach (range(1, 12) as $i) {
        $entry = RecurringEntry::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'title' => 'Plan '.$i,
            'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring',
            'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-08',
            'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'never',
            'expected_amount' => 50, 'is_amount_variable' => false, 'auto_create_transaction' => false,
        ]);
        $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-08', 'due_date' => '2026-10-08', 'expected_amount' => 50, 'status' => 'pending']);
    }
    app(CounterpartyBackfillService::class)->run($user, $parties->first(), 'EUR', true);
    $relation = CounterpartyRelation::query()->firstOrFail();
    app(CounterpartyPaymentService::class)->activate($user, $relation, 'performance-activate');
    $balanceService = app(CounterpartyBalanceService::class);
    $batchedBalances = $balanceService->forUser($user);
    foreach ($parties as $party) {
        expect($batchedBalances[$party->uuid][0])->toBe($balanceService->balance($user, $party, 'EUR'));
    }
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries): void {
        $queries[] = ['sql' => $event->sql, 'bindings' => $event->bindings, 'ms' => $event->time];
    });
    $services = [];
    foreach ([
        CounterpartyViewService::class => 'forUser',
        CounterpartyBalanceService::class => 'forUser',
        DerivedObligationsService::class => 'positions',
        MonthlyFinancialRecapService::class => 'previousClosedMonth',
        DashboardService::class => 'build',
        MonthlyTransactionSheetService::class => 'build',
        AccountVisionReportService::class => 'build',
        PeriodOverviewReportService::class => 'build',
        CategoryBreakdownReportService::class => 'build',
    ] as $class => $method) {
        $original = app($class);
        $mock = Mockery::mock($original)->makePartial();
        $mock->shouldReceive($method)->andReturnUsing(function (...$args) use ($original, $method, $class, &$services) {
            $start = hrtime(true);
            try {
                return $original->$method(...$args);
            } finally {
                $services[class_basename($class)][] = (hrtime(true) - $start) / 1e6;
            }
        });
        app()->instance($class, $mock);
    }
    $renderAt = null;
    $factory = app(ResponseFactory::class);
    $spy = Mockery::mock($factory)->makePartial();
    $spy->shouldReceive('render')->andReturnUsing(function (...$args) use ($factory, &$renderAt) {
        $renderAt = hrtime(true);

        return $factory->render(...$args);
    });
    app()->instance(ResponseFactory::class, $spy);
    $this->actingAs($user);
    $matrix = [];
    $plans = [];
    $queryBudgets = ['dashboard' => 180, 'credits-debts.index' => 200, 'shared-expenses.index' => 200, 'counterparties.show' => 160, 'recurring-entries.index' => 95];
    foreach ([
        'dashboard', 'transactions.show', 'recurring-entries.index', 'credits-debts.index',
        'shared-expenses.index', 'counterparties.show', 'tracked-items.edit', 'budget-planning', 'reports', 'reports.kpis',
        'reports.categories', 'reports.accounts', 'profile.edit', 'accounts.edit', 'notifications.index',
    ] as $name) {
        $samples = [];
        foreach (range(1, 3) as $iteration) {
            $queries = [];
            $services = [];
            $renderAt = null;
            $user->unsetRelations();
            Once::flush();
            $start = hrtime(true);
            $response = $this->get(route($name, ['year' => 2026, 'month' => 10, ...($name === 'counterparties.show' ? ['relation' => $relation->uuid] : [])]), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(PwaManifestData::class)->version()]);
            $end = hrtime(true);
            $response->assertSuccessful();
            if ($iteration > 1) {
                expect(count($queries))->toBeLessThanOrEqual($queryBudgets[$name] ?? 100, $name);
            }
            if (! in_array($name, ['dashboard', 'credits-debts.index', 'shared-expenses.index', 'counterparties.show'], true)) {
                $counterpartyQueries = collect($queries)->filter(fn ($query): bool => preg_match('/counterparty_(relations|ledger_entries|obligations|payments|allocation_entries|source_links)/', $query['sql']));
                if ($name === 'transactions.show') {
                    expect($counterpartyQueries)->toHaveCount(1, $name);
                    expect($counterpartyQueries->first()['sql'])
                        ->toContain('"counterparty_payments"', '"counterparty_source_links"', '"counterparty_allocation_entries"');
                } else {
                    expect($counterpartyQueries)->toBeEmpty($name);
                }
            }
            $duplicates = collect($queries)->groupBy(fn ($q) => $q['sql'].json_encode($q['bindings']))->sum(fn ($group) => max(0, $group->count() - 1));
            $samples[] = [
                'backend_ms' => round(($end - $start) / 1e6, 2), 'queries' => count($queries),
                'sql_ms' => round(array_sum(array_column($queries, 'ms')), 2), 'duplicates' => $duplicates,
                'payload_bytes' => strlen($response->getContent()),
                'props_ms' => $renderAt === null ? null : round(($end - $renderAt) / 1e6, 2),
                'services_ms' => array_map(fn ($times) => round(array_sum($times), 2), $services),
                'top_queries' => collect($queries)->groupBy('sql')->map(fn ($group, $sql) => ['sql' => $sql, 'count' => $group->count(), 'ms' => round($group->sum('ms'), 2)])->sortByDesc('count')->take(5)->values()->all(),
            ];
        }
        $matrix[$name] = $samples;
        if (getenv('PAGE_PERFORMANCE_OUTPUT')) {
            $mostRepeated = collect($queries)->filter(fn ($query): bool => str_starts_with(strtolower($query['sql']), 'select'))
                ->groupBy('sql')->sortByDesc(fn ($group) => $group->count())->take(2)->map(fn ($group) => $group->first());
            $plans[$name] = $mostRepeated->map(fn ($query): array => ['sql' => $query['sql'], 'plan' => DB::select((DB::connection()->getDriverName() === 'pgsql' ? 'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) ' : 'EXPLAIN QUERY PLAN ').$query['sql'], $query['bindings'])])->values()->all();
            file_put_contents(getenv('PAGE_PERFORMANCE_OUTPUT'), json_encode(['matrix' => $matrix, 'plans' => $plans], JSON_PRETTY_PRINT));
        }
    }
    expect($matrix)->toHaveCount(15);
});
