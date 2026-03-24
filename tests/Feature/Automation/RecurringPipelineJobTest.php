<?php

use App\Enums\AutomationRunStatusEnum;
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
