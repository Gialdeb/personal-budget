<?php

use App\Models\AccountMembership;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Counterparties\CounterpartyViewService;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\Dashboard\DashboardAnalysisService;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\MonthlyFinancialRecapService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Support\Pwa\PwaManifestData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;
use Ramsey\Uuid\Uuid;

test('heavy authenticated pages preserve payloads and expose their measured costs', function () {
    fake()->seed(7351);
    $uuidSequence = 0;
    Str::createUuidsUsing(function () use (&$uuidSequence) {
        return Uuid::fromString(sprintf('00000000-0000-4000-8000-%012d', ++$uuidSequence));
    });
    $this->beforeApplicationDestroyed(fn () => Str::createUuidsNormally());
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
        DashboardAnalysisService::class => 'build',
        DashboardService::class => 'build',
        MonthlyTransactionSheetService::class => 'build',
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
    $blocks = [];
    if (getenv('PAGE_PERFORMANCE_DETAIL')) {
        $class = DashboardService::class;
        $constructor = (new ReflectionClass($class))->getConstructor();
        $arguments = array_map(fn ($parameter) => app($parameter->getType()->getName()), $constructor->getParameters());
        $dashboard = Mockery::mock($class, $arguments)->makePartial()->shouldAllowMockingProtectedMethods();
        foreach (['build', 'getOverview', 'getCreditsDebtsSummary', 'getPendingActions', 'getMonthlyTrend', 'getExpenseByCategory', 'getBudgetVsActual', 'getParentCategoryBudgetStatus', 'getAccountsSummary', 'getRecurringSummary', 'getScheduledSummary', 'getIncomeByCategory', 'getMerchantBreakdown', 'getNotifications', 'formatDashboardPayload'] as $method) {
            $implementation = new ReflectionMethod($class, $method);
            $dashboard->shouldReceive($method)->andReturnUsing(function (...$arguments) use ($dashboard, $implementation, $method, &$blocks, &$queries) {
                $start = hrtime(true);
                $queryStart = count($queries);
                try {
                    return $implementation->invokeArgs($dashboard, $arguments);
                } finally {
                    $previous = $blocks[$method] ?? ['ms' => 0, 'queries' => 0, 'sql_ms' => 0, 'calls' => 0];
                    $blocks[$method] = [
                        'ms' => round($previous['ms'] + (hrtime(true) - $start) / 1e6, 2),
                        'queries' => $previous['queries'] + count($queries) - $queryStart,
                        'sql_ms' => round($previous['sql_ms'] + array_sum(array_column(array_slice($queries, $queryStart), 'ms')), 2),
                        'calls' => $previous['calls'] + 1,
                    ];
                }
            });
        }
        app()->instance($class, $dashboard);
    }
    $renderAt = null;
    $inertiaTimings = [];
    $factory = app(ResponseFactory::class);
    $spy = Mockery::mock($factory)->makePartial();
    $spy->shouldReceive('render')->andReturnUsing(function (...$args) use ($factory, &$renderAt, &$inertiaTimings) {
        $renderAt = hrtime(true);

        $original = $factory->render(...$args);
        $response = Mockery::mock(Response::class)->makePartial();
        foreach ((new ReflectionClass($original))->getProperties() as $property) {
            if (! $property->isStatic()) {
                $property->setValue($response, $property->getValue($original));
            }
        }
        foreach (['resolveProperties', 'toResponse'] as $method) {
            $implementation = new ReflectionMethod(Response::class, $method);
            $response->shouldReceive($method)->andReturnUsing(function (...$arguments) use ($response, $implementation, $method, &$inertiaTimings) {
                $start = hrtime(true);
                try {
                    return $implementation->invokeArgs($response, $arguments);
                } finally {
                    $inertiaTimings[$method] = round((hrtime(true) - $start) / 1e6, 2);
                }
            });
        }

        return $response;
    });
    app()->instance(ResponseFactory::class, $spy);
    $this->actingAs($user);
    $matrix = [];
    $plans = [];
    $navigation = [];
    $queryBudgets = ['dashboard' => 130, 'transactions.show' => 65, 'credits-debts.index' => 160, 'shared-expenses.index' => 135];
    foreach ([
        'dashboard', 'transactions.show', 'credits-debts.index', 'shared-expenses.index',
    ] as $name) {
        $samples = [];
        foreach (range(1, (int) (getenv('PAGE_PERFORMANCE_SAMPLES') ?: 5)) as $iteration) {
            $factory->flushShared();
            $queries = [];
            $services = [];
            $blocks = [];
            $inertiaTimings = [];
            $renderAt = null;
            $user->unsetRelations();
            Once::flush();
            $start = hrtime(true);
            $response = $this->get(route($name, ['year' => 2026, 'month' => 10, ...($name === 'counterparties.show' ? ['relation' => $relation->uuid] : [])]), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(PwaManifestData::class)->version()]);
            $end = hrtime(true);
            $response->assertSuccessful();
            if ($iteration > 1 && ! getenv('PAGE_PERFORMANCE_BASELINE')) {
                expect(count($queries))->toBeLessThanOrEqual($queryBudgets[$name] ?? 100, $name);
            }
            if ($name === 'transactions.show') {
                expect(collect($queries)->filter(fn ($query): bool => preg_match('/counterparty_(relations|ledger_entries|obligations|payments|allocation_entries|source_links)/', $query['sql'])))->toBeEmpty($name);
            }
            $duplicates = collect($queries)->groupBy(fn ($q) => $q['sql'].json_encode($q['bindings']))->sum(fn ($group) => max(0, $group->count() - 1));
            if ($iteration > 1 && ! getenv('PAGE_PERFORMANCE_BASELINE')) {
                $duplicateBudgets = ['dashboard' => 30, 'transactions.show' => 20, 'credits-debts.index' => 60, 'shared-expenses.index' => 45];
                expect($duplicates)->toBeLessThanOrEqual($duplicateBudgets[$name], $name);
            }
            $samples[] = [
                'backend_ms' => round(($end - $start) / 1e6, 2), 'queries' => count($queries),
                'sql_ms' => round(array_sum(array_column($queries, 'ms')), 2), 'duplicates' => $duplicates,
                'payload_bytes' => strlen($response->getContent()),
                'props_ms' => $renderAt === null ? null : round(($end - $renderAt) / 1e6, 2),
                'services_ms' => array_map(fn ($times) => round(array_sum($times), 2), $services),
                'blocks' => $blocks,
                'inertia_ms' => $inertiaTimings,
                'nested_props_bytes' => collect($response->json('props'))->filter(fn ($value, $key) => in_array($key, ['dashboard', 'monthlySheet', 'options', 'settlementOptions'], true))->map(fn ($value) => collect($value)->map(fn ($part) => strlen(json_encode($part)))->sortDesc()->all())->all(),
                'props_bytes' => collect($response->json('props'))->map(fn ($value) => strlen(json_encode($value)))->sortDesc()->all(),
                'top_queries' => collect($queries)->groupBy('sql')->map(fn ($group, $sql) => ['sql' => $sql, 'count' => $group->count(), 'ms' => round($group->sum('ms'), 2)])->sortByDesc('count')->take(5)->values()->all(),
            ];
        }
        $matrix[$name] = $samples;
        if ($name === 'transactions.show') {
            $factory->flushShared();
            $finalQueries = $queries;
            $queries = [];
            $start = hrtime(true);
            $landing = $this->get(route('transactions.index'), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(PwaManifestData::class)->version()]);
            $navigation = [
                'backend_ms' => round((hrtime(true) - $start) / 1e6, 2),
                'status' => $landing->status(), 'queries' => count($queries),
                'sql_ms' => round(array_sum(array_column($queries, 'ms')), 2),
                'location' => $landing->headers->get('Location'),
                'canonical_url' => $landing->isSuccessful() ? $landing->json('url') : null,
            ];
            if (! getenv('PAGE_PERFORMANCE_BASELINE')) {
                $landing->assertSuccessful()->assertJsonPath('url', '/transactions/2026/10');
            }
            $queries = $finalQueries;
        }
        if (getenv('PAGE_PERFORMANCE_EXPECTED')) {
            $expectedFile = getenv('PAGE_PERFORMANCE_EXPECTED').'.'.str_replace('.', '-', $name).'.props.json';
            expect($response->json('props'))->toBe(json_decode(file_get_contents($expectedFile), true));
        }
        if (getenv('PAGE_PERFORMANCE_OUTPUT')) {
            file_put_contents(getenv('PAGE_PERFORMANCE_OUTPUT').'.'.str_replace('.', '-', $name).'.props.json', json_encode($response->json('props'), JSON_PRETTY_PRINT));
        }
        if (getenv('PAGE_PERFORMANCE_OUTPUT')) {
            $groups = collect($queries)->filter(fn ($query): bool => str_starts_with(strtolower($query['sql']), 'select'))->groupBy('sql');
            $mostRepeated = $groups->sortByDesc(fn ($group) => $group->count())->take(3)
                ->merge($groups->sortByDesc(fn ($group) => $group->sum('ms'))->take(3))
                ->map(fn ($group) => $group->first());
            $plans[$name] = $mostRepeated->map(fn ($query): array => ['sql' => $query['sql'], 'plan' => DB::select((DB::connection()->getDriverName() === 'pgsql' ? 'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) ' : 'EXPLAIN QUERY PLAN ').$query['sql'], $query['bindings'])])->values()->all();
            file_put_contents(getenv('PAGE_PERFORMANCE_OUTPUT'), json_encode(['environment' => ['driver' => DB::connection()->getDriverName(), 'php' => PHP_VERSION, 'fixture' => ['transactions' => 120, 'manual_positions' => 24, 'counterparties' => 8, 'occurrences' => 12]], 'matrix' => $matrix, 'navigation' => $navigation, 'plans' => $plans], JSON_PRETTY_PRINT));
        }
    }
    expect($matrix)->toHaveCount(4);
});

test('page read snapshots are isolated by user and request and bypass mutations', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = userAccount($owner, ['name' => 'Original']);
    userAccount($other, ['name' => 'Other']);
    $accounts = app(AccessibleAccountsQuery::class);
    app()->instance('request', Request::create('/', 'GET'));
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries): void {
        $queries[] = $event->sql;
    });
    $first = $accounts->get($owner);
    $queryCount = count($queries);
    $first->first()->name = 'Changed in memory';
    expect($accounts->get($owner)->first()->name)->toBe('Original');
    expect(count($queries))->toBe($queryCount);
    expect($accounts->get($other)->pluck('name')->all())->toBe(['Other']);

    $account->update(['name' => 'Updated']);
    app()->instance('request', Request::create('/', 'GET'));
    expect($accounts->get($owner)->first()->name)->toBe('Updated');

    app()->instance('request', Request::create('/', 'POST'));
    $accounts->get($owner);
    $account->update(['name' => 'Updated again']);
    expect($accounts->get($owner)->first()->name)->toBe('Updated again');
});

test('dashboard build snapshots end with each build including changed periods and data', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
    config(['features.credits_debts.enabled' => false]);
    $user = User::factory()->create();
    UserYear::firstOrCreate(['user_id' => $user->id, 'year' => 2026]);
    $account = userAccount($user);
    $transaction = userTransaction($user, $account, ['transaction_date' => '2026-10-01', 'amount' => 10, 'direction' => 'expense']);
    $service = app(DashboardService::class);
    $first = $service->build($user, 2026, 10);
    expect($first['overview']['expense_total_raw'])->toEqual(10);
    $transaction->update(['amount' => 25]);
    expect($service->build($user, 2026, 10)['overview']['expense_total_raw'])->toEqual(25);
    expect($service->build($user, 2026, 9)['overview']['expense_total_raw'])->toEqual(0);
    expect($service->build($user, 2026, 10)['overview']['expense_total_raw'])->toEqual(25);
});

test('membership read snapshots avoid repeated queries and refresh on the next request', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $account = userAccount($owner);
    $taxonomy = app(SharedAccountCategoryTaxonomyService::class);
    $categories = app(OperationalTransactionCategoryResolver::class);
    app()->instance('request', Request::create('/', 'GET'));
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries): void {
        $queries[] = $event->sql;
    });
    expect($taxonomy->isSharedAccount($account))->toBeFalse();
    expect($categories->contributorUserIdsForAccount($account))->toBe([$owner->id]);
    $queryCount = count($queries);
    expect($taxonomy->isSharedAccount($account))->toBeFalse();
    expect($categories->contributorUserIdsForAccount($account))->toBe([$owner->id]);
    expect(count($queries))->toBe($queryCount);

    app()->instance('request', Request::create('/', 'POST'));
    expect($taxonomy->isSharedAccount($account))->toBeFalse();
    expect($categories->contributorUserIdsForAccount($account))->toBe([$owner->id]);
    AccountMembership::create([
        'account_id' => $account->id, 'user_id' => $editor->id, 'role' => 'editor',
        'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $owner->id,
    ]);
    expect($taxonomy->isSharedAccount($account))->toBeTrue();
    expect($categories->contributorUserIdsForAccount($account))->toBe([$owner->id, $editor->id]);

    app()->instance('request', Request::create('/', 'GET'));
    expect($taxonomy->isSharedAccount($account))->toBeTrue();
    expect($categories->contributorUserIdsForAccount($account))->toBe([$owner->id, $editor->id]);
});

test('dashboard timeline buckets preserve separate months across a year boundary', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00'));
    $user = User::factory()->create();
    UserYear::firstOrCreate(['user_id' => $user->id, 'year' => 2026]);
    $account = userAccount($user);
    foreach (['2025-12-12' => 10, '2026-01-12' => 20, '2026-02-12' => 30] as $date => $amount) {
        userTransaction($user, $account, ['transaction_date' => $date, 'amount' => $amount, 'direction' => 'expense']);
    }
    $data = app(DashboardService::class)->build($user, 2026, 1);
    $timeline = collect($data['analysis']['timeline'])->keyBy('key');
    $window = collect($data['analysis']['month_detail']['narrative']['window'])->keyBy('key');
    expect($timeline)->toHaveCount(12);
    expect($timeline->has('2025-12'))->toBeFalse();
    expect($timeline['2026-01']['actual_expense_raw'])->toEqual(20);
    expect($timeline['2026-02']['actual_expense_raw'])->toEqual(30);
    expect($window['2025-12']['actual_expense_raw'])->toEqual(10);
    expect($window['2026-01']['actual_expense_raw'])->toEqual(20);
    expect($window['2026-02']['actual_expense_raw'])->toEqual(30);
});
