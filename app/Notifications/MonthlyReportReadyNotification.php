<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;

class MonthlyReportReadyNotification extends LocalizedTopicNotification
{
    protected function topicKey(): string
    {
        return 'monthly_report_ready';
    }

    protected function mailMarkdownView(): string
    {
        return 'emails.notifications.topics.monthly-report-ready';
    }

    protected function mailLevel(): string
    {
        return 'success';
    }

    protected function message(object $notifiable): string
    {
        return $this->translate('message', [
            'period' => $this->formattedPeriod(),
        ]);
    }

    protected function actionLabel(object $notifiable): ?string
    {
        return $this->translate('cta');
    }

    protected function actionUrl(object $notifiable): ?string
    {
        return route('dashboard');
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function details(object $notifiable): array
    {
        return [[
            'label' => $this->translate('details.period'),
            'value' => $this->formattedPeriod(),
        ]];
    }

    protected function formattedPeriod(): string
    {
        $year = $this->payload['year'] ?? null;
        $month = $this->payload['month'] ?? null;

        if (is_numeric($year) && is_numeric($month)) {
            return CarbonImmutable::create((int) $year, (int) $month, 1)
                ->translatedFormat('F Y');
        }

        if (is_string($this->payload['period'] ?? null)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m', $this->payload['period'])
                    ->translatedFormat('F Y');
            } catch (\Throwable) {
                return $this->payload['period'];
            }
        }

        return '-';
    }
}
