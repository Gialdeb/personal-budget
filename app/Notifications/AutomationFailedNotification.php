<?php

namespace App\Notifications;

class AutomationFailedNotification extends LocalizedTopicNotification
{
    protected function topicKey(): string
    {
        return 'automation_failed';
    }

    protected function mailMarkdownView(): string
    {
        return 'emails.notifications.topics.automation-failed';
    }

    protected function mailLevel(): string
    {
        return 'error';
    }

    protected function actionLabel(object $notifiable): ?string
    {
        return $this->translate('cta');
    }

    protected function actionUrl(object $notifiable): ?string
    {
        return route('admin.automation.index');
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function details(object $notifiable): array
    {
        return array_values(array_filter([
            [
                'label' => $this->translate('details.pipeline'),
                'value' => $this->stringValue($this->payload['pipeline'] ?? null),
            ],
            [
                'label' => $this->translate('details.error_message'),
                'value' => $this->stringValue($this->payload['message'] ?? null),
            ],
            isset($this->payload['context']) ? [
                'label' => $this->translate('details.context'),
                'value' => $this->formatStructuredValue($this->payload['context']),
            ] : null,
        ]));
    }
}
