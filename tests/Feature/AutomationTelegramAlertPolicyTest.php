<?php

use App\DTO\Automation\AutomationAlertData;
use App\Services\Automation\AutomationAlertService;
use App\Services\Automation\Channels\LogAutomationAlertChannel;
use App\Services\Automation\Channels\TelegramAutomationAlertChannel;
use App\Services\Communication\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config()->set('automation.alerts.enabled', true);
});

it('sends telegram only for real failed runs and deduplicates the same run', function () {
    $logChannel = Mockery::mock(LogAutomationAlertChannel::class);
    $logChannel->shouldReceive('send')->twice();

    $telegramChannel = Mockery::mock(TelegramAutomationAlertChannel::class);
    $telegramChannel->shouldReceive('send')->once();

    $domainNotifications = Mockery::mock(DomainNotificationService::class);
    $domainNotifications->shouldReceive('sendAutomationFailed')->twice();

    $service = new AutomationAlertService(
        $logChannel,
        $telegramChannel,
        $domainNotifications,
    );

    $alert = new AutomationAlertData(
        type: 'failed_run',
        pipeline: 'recurring_pipeline',
        title: 'Automation pipeline failed',
        message: 'Boom',
        context: ['run_uuid' => 'run-123'],
    );

    $service->send($alert);
    $service->send($alert);
});

it('does not send telegram for success, skipped, stale or missing run alerts', function () {
    $logChannel = Mockery::mock(LogAutomationAlertChannel::class);
    $logChannel->shouldReceive('send')->times(4);

    $telegramChannel = Mockery::mock(TelegramAutomationAlertChannel::class);
    $telegramChannel->shouldNotReceive('send');

    $domainNotifications = Mockery::mock(DomainNotificationService::class);
    $domainNotifications->shouldReceive('sendAutomationFailed')->times(2);

    $service = new AutomationAlertService(
        $logChannel,
        $telegramChannel,
        $domainNotifications,
    );

    $service->send(new AutomationAlertData(
        type: 'success',
        pipeline: 'recurring_pipeline',
        title: 'Success',
        message: 'Done',
    ));
    $service->send(new AutomationAlertData(
        type: 'skipped',
        pipeline: 'recurring_pipeline',
        title: 'Skipped',
        message: 'Nothing to do',
    ));
    $service->send(new AutomationAlertData(
        type: 'stale_run',
        pipeline: 'recurring_pipeline',
        title: 'Stale',
        message: 'Old run',
    ));
    $service->send(new AutomationAlertData(
        type: 'missing_run',
        pipeline: 'recurring_pipeline',
        title: 'Missing',
        message: 'No run',
    ));
});
