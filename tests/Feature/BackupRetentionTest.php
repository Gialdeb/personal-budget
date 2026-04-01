<?php

use App\Enums\AutomationRunStatusEnum;
use App\Jobs\Automation\RunBackupRetentionCleanupJob;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Automation\Backups\BackupRetentionService;
use App\Services\Automation\Backups\FullBackupService;
use App\Services\Automation\Backups\UserBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Http::fake();

    config()->set('automation.backups.disk', 'local');
    config()->set('automation.backups.retention.enabled', true);
    config()->set('automation.backups.retention.full.enabled', true);
    config()->set('automation.backups.retention.full.days', 30);
    config()->set('automation.backups.retention.user.enabled', true);
    config()->set('automation.backups.retention.user.days', 30);
});

it('deletes only expired managed backup archives and keeps recent or unmanaged files', function () {
    $oldFull = createManagedBackup('backups/full/'.FullBackupService::ARCHIVE_PREFIX.'20250101_000000.zip', 120);
    $recentFull = createManagedBackup('backups/full/'.FullBackupService::ARCHIVE_PREFIX.'20260320_000000.zip', 5);
    $oldUser = createManagedBackup('backups/users/'.UserBackupService::ARCHIVE_PREFIX.'20250101_000000.zip', 120);
    $recentUser = createManagedBackup('backups/users/'.UserBackupService::ARCHIVE_PREFIX.'20260320_000000.zip', 5);
    $foreignFile = createManagedBackup('backups/full/manual-note.zip', 120);
    $outsideScope = createManagedBackup('exports/full-backup-20250101_000000.zip', 120);

    $result = app(BackupRetentionService::class)->run();

    expect(Storage::disk('local')->exists($oldFull))->toBeFalse()
        ->and(Storage::disk('local')->exists($oldUser))->toBeFalse()
        ->and(Storage::disk('local')->exists($recentFull))->toBeTrue()
        ->and(Storage::disk('local')->exists($recentUser))->toBeTrue()
        ->and(Storage::disk('local')->exists($foreignFile))->toBeTrue()
        ->and(Storage::disk('local')->exists($outsideScope))->toBeTrue()
        ->and($result['deleted_count'])->toBe(2)
        ->and($result['kept_count'])->toBe(2)
        ->and($result['inspected_count'])->toBe(4);
});

it('tracks a successful retention cleanup run without sending a telegram success message', function () {
    createManagedBackup('backups/full/'.FullBackupService::ARCHIVE_PREFIX.'20250101_000000.zip', 120);

    config()->set('automation.alerts.enabled', true);
    config()->set('automation.alerts.telegram.enabled', true);
    config()->set('automation.alerts.telegram.bot_token', 'cleanup-token');
    config()->set('automation.alerts.telegram.chat_id', '999999');

    app(RunBackupRetentionCleanupJob::class)->handle(
        app(AutomationPipelineRunner::class),
        app(BackupRetentionService::class),
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->pipeline)->toBe('backup_retention_cleanup')
        ->and($run->result['deleted_count'])->toBe(1);

    Http::assertNothingSent();
});

it('tracks a failed retention cleanup run and sends a real failure telegram alert', function () {
    config()->set('automation.alerts.enabled', true);
    config()->set('automation.alerts.telegram.enabled', true);
    config()->set('automation.alerts.telegram.bot_token', 'cleanup-token');
    config()->set('automation.alerts.telegram.chat_id', '999999');

    $failingService = Mockery::mock(BackupRetentionService::class);
    $failingService->shouldReceive('run')->once()->andThrow(new RuntimeException('Cleanup delete failed'));

    app(RunBackupRetentionCleanupJob::class)->handle(
        app(AutomationPipelineRunner::class),
        $failingService,
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe(AutomationRunStatusEnum::FAILED)
        ->and($run->pipeline)->toBe('backup_retention_cleanup')
        ->and($run->error_message)->toBe('Cleanup delete failed');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), 'api.telegram.org/botcleanup-token/sendMessage')
            && str_contains($data['text'] ?? '', 'Automazione fallita')
            && str_contains($data['text'] ?? '', 'backup_retention_cleanup')
            && str_contains($data['text'] ?? '', 'Cleanup delete failed');
    });
});

function createManagedBackup(string $path, int $ageInDays): string
{
    Storage::disk('local')->put($path, 'backup');
    touch(Storage::disk('local')->path($path), now()->subDays($ageInDays)->getTimestamp());

    return $path;
}
