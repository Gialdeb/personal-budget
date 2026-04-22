<?php

use App\Enums\AutomationRunStatusEnum;
use App\Jobs\Automation\RunFullBackupJob;
use App\Jobs\Automation\RunUserBackupJob;
use App\Models\AutomationRun;
use App\Models\User;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Automation\Backups\FullBackupService;
use App\Services\Automation\Backups\UserBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('automation.alerts.enabled', true);
    config()->set('automation.alerts.telegram.enabled', true);
    config()->set('automation.alerts.telegram.bot_token', 'backup-token');
    config()->set('automation.alerts.telegram.chat_id', '999999');

    Storage::fake('local');
    Storage::fake('public');
    Http::fake();
});

it('runs a full backup and stores an honest application snapshot manifest without sending a telegram success alert', function () {
    Storage::disk('local')->put('documents/example.txt', 'private');
    Storage::disk('public')->put('avatars/example.txt', 'public');

    app(RunFullBackupJob::class)->handle(
        app(AutomationPipelineRunner::class),
        app(FullBackupService::class),
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->pipeline)->toBe('full_backup')
        ->and($run->context['environment'])->toBe(app()->environment())
        ->and($run->context['backup_disk'])->toBe('local')
        ->and($run->context['backup_root'])->toContain('framework/testing/disks/local')
        ->and($run->result['path'])->not->toBeEmpty()
        ->and($run->result['absolute_path'])->toContain('framework/testing/disks/local/backups/full/')
        ->and(Storage::disk('local')->exists($run->result['path']))->toBeTrue()
        ->and($run->result['table_count'])->toBeGreaterThan(0)
        ->and($run->result['restore_capability'])->toBe('manual_rebuild_required');

    $archive = openArchive($run->result['path']);
    $manifest = readArchiveJson($archive, 'manifest.json');

    expect($manifest['artifact_classification'])->toBe('application_snapshot_archive')
        ->and($manifest['restore_capability']['is_end_to_end_restorable'])->toBeFalse()
        ->and($manifest['restore_capability']['level'])->toBe('manual_rebuild_required')
        ->and($archive->locateName('database/users.json') !== false)->toBeTrue()
        ->and($archive->locateName('storage/local/documents/example.txt') !== false)->toBeTrue()
        ->and($archive->locateName('storage/public/avatars/example.txt') !== false)->toBeTrue();

    $archive->close();

    Http::assertNothingSent();
});

it('runs a user backup with a structured snapshot that is readable for a future targeted restore without sending a telegram success alert', function () {
    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);

    app(RunUserBackupJob::class)->handle(
        app(AutomationPipelineRunner::class),
        app(UserBackupService::class),
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->pipeline)->toBe('user_backup')
        ->and($run->context['environment'])->toBe(app()->environment())
        ->and($run->context['backup_disk'])->toBe('local')
        ->and($run->context['backup_root'])->toContain('framework/testing/disks/local')
        ->and($run->result['user_count'])->toBe(1)
        ->and($run->result['absolute_path'])->toContain('framework/testing/disks/local/backups/users/')
        ->and(Storage::disk('local')->exists($run->result['path']))->toBeTrue()
        ->and($run->result['restore_capability'])->toBe('structured_export_for_targeted_restore');

    $archive = openArchive($run->result['path']);
    $manifest = readArchiveJson($archive, 'manifest.json');
    $snapshot = readArchiveJson($archive, 'users/'.$user->uuid.'/data.json');

    expect($manifest['artifact_classification'])->toBe('user_snapshot_archive')
        ->and($manifest['restore_capability']['is_automated_restore_available'])->toBeFalse()
        ->and($manifest['restore_capability']['level'])->toBe('structured_export_for_targeted_restore')
        ->and($snapshot['profile']['email'])->toBe('mario@example.com')
        ->and($snapshot['profile']['name'])->toBe('Mario')
        ->and($snapshot)->toHaveKeys([
            'profile',
            'user_settings',
            'notification_preferences',
            'accounts',
            'transactions',
            'budgets',
        ]);

    $archive->close();

    Http::assertNothingSent();
});

it('tracks a failed full backup and sends a failure telegram alert', function () {
    $failingService = Mockery::mock(FullBackupService::class);
    $failingService->shouldReceive('run')->once()->andThrow(new RuntimeException('Archive creation failed'));

    app(RunFullBackupJob::class)->handle(
        app(AutomationPipelineRunner::class),
        $failingService,
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe(AutomationRunStatusEnum::FAILED)
        ->and($run->context['environment'])->toBe(app()->environment())
        ->and($run->error_message)->toBe('Archive creation failed');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($data['text'] ?? '', 'Backup completo fallito')
            && str_contains($data['text'] ?? '', 'Archive creation failed')
            && str_contains($data['text'] ?? '', '<b>Ambiente:</b> <code>'.app()->environment().'</code>');
    });
});

function openArchive(string $relativePath): ZipArchive
{
    $archive = new ZipArchive;
    $openResult = $archive->open(Storage::disk('local')->path($relativePath));

    expect($openResult)->toBeTrue();

    return $archive;
}

function readArchiveJson(ZipArchive $archive, string $entry): array
{
    $content = $archive->getFromName($entry);

    expect($content)->not->toBeFalse();

    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
}
