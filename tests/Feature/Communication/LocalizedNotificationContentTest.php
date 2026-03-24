<?php

use App\Models\User;
use App\Notifications\AutomationFailedNotification;
use App\Notifications\ImportCompletedNotification;
use App\Notifications\MonthlyReportReadyNotification;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\App;

it('localizes automation failed notification content in english and renders the shared email template', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    App::setLocale('it');

    $notification = new AutomationFailedNotification([
        'pipeline' => 'recurring_pipeline',
        'message' => 'Recurring pipeline exploded',
        'context' => ['run_uuid' => 'run-123'],
    ]);

    $mail = $notification->toMail($user);
    $database = $notification->toDatabase($user);
    $html = app(Markdown::class)->render($mail->markdown, $mail->viewData)->toHtml();

    expect($mail->subject)->toBe('Automation pipeline failed')
        ->and($mail->markdown)->toBe('emails.notifications.topics.automation-failed')
        ->and($mail->viewData['detailsTitle'])->toBe('Details')
        ->and($mail->viewData['actionLabel'])->toBe('Open automations')
        ->and($database['topic'])->toBe('automation_failed')
        ->and($database['topic_label'])->toBe('Automation failure')
        ->and($database['title'])->toBe('Automation pipeline failed')
        ->and($database['message'])->toBe('One of the automation pipelines requires attention.')
        ->and($html)->toContain('Automation pipeline failed')
        ->and($html)->toContain('Recurring pipeline exploded')
        ->and($html)->toContain('run-123');
});

it('localizes import completed notification content in italian', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);

    App::setLocale('en');

    $notification = new ImportCompletedNotification([
        'import_uuid' => 'imp-123',
        'original_filename' => 'estratto-conto.csv',
        'rows_count' => 42,
        'imported_rows_count' => 39,
    ]);

    $mail = $notification->toMail($user);
    $database = $notification->toDatabase($user);
    $html = app(Markdown::class)->render($mail->markdown, $mail->viewData)->toHtml();

    expect($mail->subject)->toBe('Import completato')
        ->and($mail->markdown)->toBe('emails.notifications.topics.import-completed')
        ->and($database['topic'])->toBe('import_completed')
        ->and($database['title'])->toBe('Import completato')
        ->and($database['message'])->toBe('Il tuo import è stato completato con successo.')
        ->and($html)->toContain('Import completato')
        ->and($html)->toContain('estratto-conto.csv')
        ->and($html)->toContain('39')
        ->and($html)->toContain('42');
});

it('localizes monthly report ready notification content using the user locale', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    App::setLocale('it');

    $notification = new MonthlyReportReadyNotification([
        'year' => 2026,
        'month' => 3,
        'period' => '2026-03',
    ]);

    $mail = $notification->toMail($user);
    $database = $notification->toDatabase($user);
    $html = app(Markdown::class)->render($mail->markdown, $mail->viewData)->toHtml();

    expect($mail->subject)->toBe('Monthly report ready')
        ->and($database['topic'])->toBe('monthly_report_ready')
        ->and($database['title'])->toBe('Monthly report ready')
        ->and($database['message'])->toBe('Your monthly report for March 2026 is ready.')
        ->and($html)->toContain('March 2026')
        ->and($html)->toContain('Open dashboard');
});
