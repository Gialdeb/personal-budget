<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Http\Resources\Admin\AutomationRunResource;
use App\Jobs\Automation\CheckAutomationHealthJob;
use App\Jobs\Automation\RunHorizonSnapshotJob;
use App\Jobs\Automation\RunImportsPruneOldJob;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationAlertService;
use App\Services\Automation\AutomationPipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('automation.alerts.enabled', false);
});

it('tracks imports prune old runs through automation telemetry', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->andReturn(0);
    Artisan::shouldReceive('output')
        ->once()
        ->andReturn("Pruned 1 import source files.\nDeleted 2 old imports.");

    app(RunImportsPruneOldJob::class)->handle(app(AutomationPipelineRunner::class));

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->automation_key)->toBe('imports_prune_old')
        ->and($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->context['environment'])->toBe(app()->environment())
        ->and($run->result['command'])->toBe('imports:prune-old')
        ->and($run->result['exit_code'])->toBe(0);
});

it('tracks horizon snapshot runs through automation telemetry', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('horizon:snapshot')
        ->andReturn(0);
    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('Snapshot stored.');

    app(RunHorizonSnapshotJob::class)->handle(app(AutomationPipelineRunner::class));

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->automation_key)->toBe('horizon_snapshot')
        ->and($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->context['environment'])->toBe(app()->environment())
        ->and($run->result['command'])->toBe('horizon:snapshot')
        ->and($run->result['exit_code'])->toBe(0);
});

it('tracks automation health check runs with a minimal recorded heartbeat', function () {
    config()->set('automation.pipelines', [
        'automation_health_check' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 30,
            'supports_reference_date' => false,
        ],
    ]);

    app(CheckAutomationHealthJob::class)->handle(
        app(AutomationAlertService::class),
        app(AutomationPipelineRunner::class),
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->automation_key)->toBe('automation_health_check')
        ->and($run->trigger_type)->toBe(AutomationTriggerTypeEnum::SYSTEM)
        ->and($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->context['environment'])->toBe(app()->environment())
        ->and($run->result['checked_pipelines'])->toBe(1);
});

it('marks backup artifacts as unavailable in admin resources when the file is gone', function () {
    Storage::fake('local');

    $run = AutomationRun::query()->create([
        'automation_key' => 'full_backup',
        'pipeline' => 'full_backup',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'context' => [
            'environment' => app()->environment(),
            'backup_disk' => 'local',
        ],
        'result' => [
            'path' => 'backups/full/full-backup-missing.zip',
            'absolute_path' => Storage::disk('local')->path('backups/full/full-backup-missing.zip'),
        ],
    ]);

    $resource = (new AutomationRunResource($run))->resolve();

    expect($resource['backup_artifact'])->toBeArray()
        ->and($resource['backup_artifact']['disk'])->toBe('local')
        ->and($resource['backup_artifact']['is_available'])->toBeFalse();
});
