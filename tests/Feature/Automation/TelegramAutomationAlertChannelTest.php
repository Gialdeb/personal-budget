<?php

use App\DTO\Automation\AutomationAlertData;
use App\Services\Automation\Channels\TelegramAutomationAlertChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('sends telegram alert when enabled', function () {
    config()->set('automation.alerts.telegram.enabled', true);
    config()->set('automation.alerts.telegram.bot_token', 'test-token');
    config()->set('automation.alerts.telegram.chat_id', '123456');

    Http::fake();

    $channel = app(TelegramAutomationAlertChannel::class);

    $channel->send(new AutomationAlertData(
        type: 'failed_run',
        pipeline: 'recurring_pipeline',
        title: 'Automation pipeline failed',
        message: 'Recurring pipeline exploded',
        context: ['run_uuid' => 'abc-123'],
    ));

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), 'api.telegram.org/bottest-token/sendMessage')
            && ($data['chat_id'] ?? null) === '123456'
            && ($data['parse_mode'] ?? null) === 'HTML'
            && str_contains($data['text'] ?? '', 'Automazione fallita')
            && str_contains($data['text'] ?? '', 'recurring_pipeline');
    });
});

it('does not send telegram alert when disabled', function () {
    config()->set('automation.alerts.telegram.enabled', false);

    Http::fake();

    $channel = app(TelegramAutomationAlertChannel::class);

    $channel->send(new AutomationAlertData(
        type: 'failed_run',
        pipeline: 'recurring_pipeline',
        title: 'Automation pipeline failed',
        message: 'Recurring pipeline exploded',
    ));

    Http::assertNothingSent();
});
