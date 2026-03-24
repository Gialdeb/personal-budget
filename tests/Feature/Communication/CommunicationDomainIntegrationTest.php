<?php

use App\DTO\Automation\AutomationAlertData;
use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Enums\NotificationAudienceEnum;
use App\Models\Import;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Notifications\AutomationFailedNotification;
use App\Notifications\ImportCompletedNotification;
use App\Notifications\MonthlyReportReadyNotification;
use App\Services\Automation\AutomationAlertService;
use App\Services\Communication\DomainNotificationService;
use App\Services\Communication\MonthlyReportNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    NotificationTopic::query()->create([
        'key' => 'automation_failed',
        'name' => 'Automation failed',
        'audience' => NotificationAudienceEnum::ADMIN,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => true,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
    ]);

    NotificationTopic::query()->create([
        'key' => 'import_completed',
        'name' => 'Import completed',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => false,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
    ]);

    NotificationTopic::query()->create([
        'key' => 'monthly_report_ready',
        'name' => 'Monthly report ready',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => true,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
    ]);
});

it('sends automation failed notification to admins through the domain layer', function () {
    Notification::fake();

    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    app(DomainNotificationService::class)->sendAutomationFailed([
        'pipeline' => 'recurring_pipeline',
        'message' => 'Boom',
    ]);

    Notification::assertSentTo($admin, AutomationFailedNotification::class);
});

it('sends import completed notification to the import owner', function () {
    Notification::fake();

    $user = User::factory()->create();

    $import = Import::query()->create([
        'user_id' => $user->id,
        'original_filename' => 'test.csv',
        'stored_filename' => 'imports/test.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::COMPLETED,
        'rows_count' => 10,
        'imported_rows_count' => 8,
    ]);

    app(DomainNotificationService::class)->sendImportCompleted($import);

    Notification::assertSentTo($user, ImportCompletedNotification::class);
});

it('sends monthly report ready notification to the user', function () {
    Notification::fake();

    $user = User::factory()->create();

    app(MonthlyReportNotificationService::class)->notifyReady($user, 2026, 3);

    Notification::assertSentTo($user, MonthlyReportReadyNotification::class);
});

it('sends automation alert through automation alert service into the notification layer', function () {
    Notification::fake();

    config()->set('automation.alerts.enabled', true);
    config()->set('automation.alerts.telegram.enabled', false);

    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    app(AutomationAlertService::class)->send(new AutomationAlertData(
        type: 'failed_run',
        pipeline: 'recurring_pipeline',
        title: 'Automation pipeline failed',
        message: 'Recurring pipeline exploded',
        context: ['run_uuid' => 'abc-123'],
    ));

    Notification::assertSentTo($admin, AutomationFailedNotification::class);
});
