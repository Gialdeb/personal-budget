<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('filters automation runs by pipeline and status for admin', function () {
    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    $failedRun = AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::FAILED,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
    ]);

    AutomationRun::query()->create([
        'automation_key' => 'reports_pipeline',
        'pipeline' => 'reports_pipeline',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::MANUAL,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.automation.index', [
            'pipeline' => 'recurring_pipeline',
            'status' => 'failed',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Automation/Index')
            ->where('filters.pipeline', 'recurring_pipeline')
            ->where('filters.status', 'failed')
            ->has('statuses', 4)
            ->has('runs.data', 1)
            ->where('runs.data.0.uuid', $failedRun->uuid)
            ->where('runs.data.0.is_retryable', true)
        );
});

it('renders automation run details for admin', function () {
    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    $run = AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::WARNING,
        'trigger_type' => AutomationTriggerTypeEnum::RETRY,
        'processed_count' => 10,
        'success_count' => 8,
        'warning_count' => 1,
        'error_count' => 1,
        'context' => ['source' => 'admin'],
        'result' => [
            'message' => 'partial',
            'account_results' => [
                [
                    'account_name' => 'Carta principale',
                    'status' => 'execution_error',
                    'technical_error' => true,
                    'detail' => 'Impossibile creare il regolamento tecnico.',
                    'cycle_end_date' => '2026-02-15',
                    'payment_due_date' => '2026-02-16',
                    'charged_amount' => null,
                    'exception_class' => RuntimeException::class,
                ],
            ],
        ],
        'error_message' => 'Something happened',
        'exception_class' => RuntimeException::class,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.automation.show', $run))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Automation/Show')
            ->where('run.uuid', $run->uuid)
            ->where('run.is_retryable', true)
            ->where('run.context.source', 'admin')
            ->where('run.result.message', 'partial')
            ->where('run.result.account_results.0.account_name', 'Carta principale')
            ->where('run.result.account_results.0.status', 'execution_error'));
});
