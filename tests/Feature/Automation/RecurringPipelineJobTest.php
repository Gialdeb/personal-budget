<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Models\AccountType;
use App\Models\AutomationRun;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\Scope;
use App\Models\User;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Transactions\RecurringEntryLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('runs recurring pipeline job and records automation run', function () {
    $service = Mockery::mock(RecurringEntryLifecycleService::class);
    $service->shouldReceive('runAutomationPipeline')
        ->once()
        ->andReturn([
            'processed_count' => 5,
            'success_count' => 5,
            'warning_count' => 0,
            'error_count' => 0,
            'generated_occurrences' => 2,
            'created_transactions' => 3,
        ]);

    $this->app->instance(RecurringEntryLifecycleService::class, $service);

    $job = app(RunRecurringPipelineJob::class);
    $job->handle(
        app(AutomationPipelineRunner::class),
        $service,
    );

    $run = AutomationRun::query()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run->automation_key)->toBe('recurring_pipeline')
        ->and($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->processed_count)->toBe(5);
});

it('runs recurring pipeline job through real lifecycle services and records generated and posted counts', function () {
    AccountType::query()->firstOrCreate([
        'code' => 'payment_account',
    ], [
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-01-02 10:00:00'));

    $user = User::factory()->create();
    $account = userAccount($user, [
        'opening_balance' => '1000.00',
        'current_balance' => '1000.00',
    ]);
    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Famiglia',
        'type' => 'household',
        'color' => '#000000',
        'is_active' => true,
    ]);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Affitti',
        'slug' => 'affitti-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => $scope->id,
        'category_id' => $category->id,
        'title' => 'Rent recurring entry',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => '49.90',
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => null,
        'start_date' => '2026-01-01',
        'next_occurrence_date' => '2026-01-01',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => true,
        'is_active' => true,
    ]);

    $job = app(RunRecurringPipelineJob::class);
    $job->handle(
        app(AutomationPipelineRunner::class),
        app(RecurringEntryLifecycleService::class),
    );

    $run = AutomationRun::query()->latest('id')->first();
    $entry = RecurringEntry::query()->firstOrFail();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->processed_count)->toBe(4)
        ->and($run->success_count)->toBe(4)
        ->and($run->warning_count)->toBe(0)
        ->and($run->error_count)->toBe(0)
        ->and($run->result['generated_occurrences'])->toBe(2)
        ->and($run->result['created_transactions'])->toBe(2)
        ->and($entry->occurrences()->count())->toBe(2)
        ->and($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(2);
});

it('isolates recurring workers and orders their timeout and reservation limits', function () {
    $job = new RunRecurringPipelineJob;
    $supervisor = config('horizon.defaults.supervisor-recurring');
    $connection = config('queue.connections.'.$job->connection);

    expect($job->queue)->toBe('recurring')
        ->and($supervisor['connection'])->toBe($job->connection)
        ->and($supervisor['queue'])->toBe([$job->queue])
        ->and($supervisor['timeout'])->toBeGreaterThan($job->timeout)
        ->and($connection['retry_after'])->toBeGreaterThan($supervisor['timeout'])
        ->and(config('horizon.environments.production'))->toHaveKey('supervisor-recurring')
        ->and(config('horizon.environments.local'))->toHaveKey('supervisor-recurring')
        ->and(config('horizon.defaults.supervisor-1.connection'))->toBe('redis')
        ->and(config('horizon.defaults.supervisor-1.queue'))->not->toContain($job->queue);

    foreach (['docker-compose.yml', 'docker-compose.production.yml'] as $file) {
        expect(file_get_contents(base_path($file)))->toMatch('/horizon:\n(?:(?!\n  \w)[\s\S])*?stop_grace_period: 180s/');
    }
});

it('prevents overlapping recurring jobs across triggers and releases the lock afterwards', function () {
    config(['cache.stores.redis' => ['driver' => 'array']]);
    $scheduled = new RunRecurringPipelineJob;
    $manual = new RunRecurringPipelineJob(AutomationTriggerTypeEnum::MANUAL);
    $processed = 0;

    $scheduled->middleware()[0]($scheduled, function () use ($manual, &$processed): void {
        $processed++;
        $manual->middleware()[0]($manual, function () use (&$processed): void {
            $processed++;
        });
    });

    expect($processed)->toBe(1);

    $manual->middleware()[0]($manual, function () use (&$processed): void {
        $processed++;
    });

    expect($processed)->toBe(2);
});

it('releases the recurring lock after failure and expires abandoned locks', function () {
    config(['cache.stores.redis' => ['driver' => 'array']]);
    $job = new RunRecurringPipelineJob;
    $middleware = $job->middleware()[0];
    expect(fn () => $middleware($job, function (): void {
        throw new RuntimeException('Pipeline failed');
    }))->toThrow(RuntimeException::class, 'Pipeline failed');

    $cache = Cache::store('redis');
    $abandoned = $cache->lock('recurring-pipeline', 180);
    expect($abandoned->get())->toBeTrue();
    $processed = false;
    $middleware($job, function () use (&$processed): void {
        $processed = true;
    });
    expect($processed)->toBeFalse();
    $this->travel(181)->seconds();
    $middleware($job, function () use (&$processed): void {
        $processed = true;
    });
    expect($processed)->toBeTrue();
});
