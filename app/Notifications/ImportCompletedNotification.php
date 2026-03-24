<?php

namespace App\Notifications;

class ImportCompletedNotification extends LocalizedTopicNotification
{
    protected function topicKey(): string
    {
        return 'import_completed';
    }

    protected function mailMarkdownView(): string
    {
        return 'emails.notifications.topics.import-completed';
    }

    protected function mailLevel(): string
    {
        return 'success';
    }

    protected function actionLabel(object $notifiable): ?string
    {
        return isset($this->payload['import_uuid']) ? $this->translate('cta') : null;
    }

    protected function actionUrl(object $notifiable): ?string
    {
        if (! isset($this->payload['import_uuid'])) {
            return null;
        }

        return route('imports.show', $this->payload['import_uuid']);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function details(object $notifiable): array
    {
        return array_values(array_filter([
            [
                'label' => $this->translate('details.import_uuid'),
                'value' => $this->stringValue($this->payload['import_uuid'] ?? null),
            ],
            isset($this->payload['original_filename']) ? [
                'label' => $this->translate('details.filename'),
                'value' => $this->stringValue($this->payload['original_filename']),
            ] : null,
            isset($this->payload['imported_rows_count']) ? [
                'label' => $this->translate('details.imported_rows_count'),
                'value' => $this->stringValue($this->payload['imported_rows_count']),
            ] : null,
            isset($this->payload['rows_count']) ? [
                'label' => $this->translate('details.rows_count'),
                'value' => $this->stringValue($this->payload['rows_count']),
            ] : null,
        ]));
    }
}
