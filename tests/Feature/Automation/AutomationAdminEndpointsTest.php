<?php

use App\Enums\AutomationTriggerTypeEnum;
use App\Jobs\Automation\RunCreditCardAutopayJob;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows admin to dispatch a pipeline manually', function () {
    Bus::fake();

    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    $this->actingAs($admin)
        ->post(route('admin.automation.run', ['pipeline' => 'recurring_pipeline']))
        ->assertSessionHas('success');

    Bus::assertDispatched(RunRecurringPipelineJob::class, function ($job) {
        return $job->triggerType === AutomationTriggerTypeEnum::MANUAL;
    });
});

it('allows admin to dispatch the credit card autopay pipeline manually with a reference date', function () {
    Bus::fake();

    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    $this->actingAs($admin)
        ->post(route('admin.automation.run', ['pipeline' => 'credit_card_autopay']), [
            'reference_date' => '2026-02-16',
        ])
        ->assertSessionHas('success');

    Bus::assertDispatched(RunCreditCardAutopayJob::class, function ($job) {
        return $job->triggerType === AutomationTriggerTypeEnum::MANUAL
            && $job->referenceDate === '2026-02-16';
    });
});
