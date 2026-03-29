<?php

namespace App\Notifications;

use App\Enums\CommunicationChannelEnum;
use App\Services\Communication\CommunicationVariableResolver;
use Carbon\CarbonImmutable;

class CreditCardAutopayCompletedNotification extends LocalizedTopicNotification
{
    protected function topicKey(): string
    {
        return 'credit_card_autopay_completed';
    }

    protected function mailMarkdownView(): string
    {
        return 'emails.notifications.topics.credit-card-autopay-completed';
    }

    protected function mailLevel(): string
    {
        return 'success';
    }

    protected function title(object $notifiable): string
    {
        return $this->resolvedTopicString('title', $notifiable);
    }

    protected function message(object $notifiable): string
    {
        return $this->resolvedTopicString('message', $notifiable);
    }

    protected function actionLabel(object $notifiable): ?string
    {
        return $this->translate('cta');
    }

    protected function actionUrl(object $notifiable): ?string
    {
        $paymentDate = $this->paymentDueDate();

        if (! $paymentDate instanceof CarbonImmutable) {
            return null;
        }

        return route('transactions.show', [
            'year' => $paymentDate->year,
            'month' => $paymentDate->month,
        ]);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function details(object $notifiable): array
    {
        return array_values(array_filter([
            [
                'label' => $this->translate('details.credit_card_account'),
                'value' => $this->stringValue($this->payload['credit_card_account_name'] ?? null),
            ],
            [
                'label' => $this->translate('details.linked_payment_account'),
                'value' => $this->stringValue($this->payload['linked_payment_account_name'] ?? null),
            ],
            [
                'label' => $this->translate('details.amount'),
                'value' => $this->formattedAmount($notifiable),
            ],
            [
                'label' => $this->translate('details.payment_due_date'),
                'value' => $this->formattedDate($this->payload['payment_due_date'] ?? null),
            ],
            [
                'label' => $this->translate('details.cycle_end_date'),
                'value' => $this->formattedDate($this->payload['cycle_end_date'] ?? null),
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadForTemplate(object $notifiable): array
    {
        return array_merge(parent::payloadForTemplate($notifiable), [
            'charged_amount_formatted' => $this->formattedAmount($notifiable),
            'payment_due_date_formatted' => $this->formattedDate($this->payload['payment_due_date'] ?? null),
            'cycle_end_date_formatted' => $this->formattedDate($this->payload['cycle_end_date'] ?? null),
        ]);
    }

    /**
     * @return array{subject: string, title: string, body: string, cta_label: ?string, cta_url: ?string}
     */
    protected function resolvedContent(object $notifiable, CommunicationChannelEnum $channel): array
    {
        $content = parent::resolvedContent($notifiable, $channel);
        $payload = $this->payloadForTemplate($notifiable);
        $resolver = app(CommunicationVariableResolver::class);

        foreach (['subject', 'title', 'body', 'cta_label'] as $key) {
            if (is_string($content[$key] ?? null) && $content[$key] !== '') {
                $content[$key] = $resolver->replacePlaceholders($content[$key], $payload);
            }
        }

        return $content;
    }

    protected function resolvedTopicString(string $suffix, object $notifiable): string
    {
        return app(CommunicationVariableResolver::class)->replacePlaceholders(
            $this->translate($suffix),
            $this->payloadForTemplate($notifiable),
        );
    }

    protected function formattedAmount(object $notifiable): string
    {
        $amount = round((float) ($this->payload['charged_amount'] ?? 0), 2);
        $currency = $this->stringValue($this->payload['currency'] ?? null, 'EUR');
        $formatLocale = is_string($notifiable->format_locale ?? null) && $notifiable->format_locale !== ''
            ? $notifiable->format_locale
            : 'en_US';
        $formatter = new \NumberFormatter(str_replace('-', '_', $formatLocale), \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount, $currency);

        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }

        return number_format($amount, 2, '.', ',').' '.$currency;
    }

    protected function formattedDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function paymentDueDate(): ?CarbonImmutable
    {
        $value = $this->payload['payment_due_date'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
