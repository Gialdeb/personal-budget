<?php

namespace App\Notifications;

use App\Enums\CommunicationChannelEnum;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\CommunicationTemplateResolver;
use App\Services\Communication\NotificationPreferenceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

abstract class LocalizedTopicNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected array $payload = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $topic = NotificationTopic::query()
            ->where('key', $this->topicKey())
            ->where('is_active', true)
            ->first();

        if (! $topic) {
            return [];
        }

        return array_map(
            fn ($channel) => $channel->value,
            app(NotificationPreferenceResolver::class)->resolveChannels($notifiable, $topic)
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->withLocale($notifiable, function (object $notifiable): MailMessage {
            $content = $this->resolvedContent($notifiable, CommunicationChannelEnum::MAIL);
            $mail = (new MailMessage)
                ->subject($content['subject'])
                ->markdown($this->mailMarkdownView(), [
                    'title' => $content['title'],
                    'message' => $content['body'],
                    'details' => $this->details($notifiable),
                    'detailsTitle' => __('notifications.common.details'),
                    'actionLabel' => $content['cta_label'],
                    'actionUrl' => $content['cta_url'],
                    'footer' => __('notifications.common.footer', ['app' => config('app.name')]),
                    'appName' => config('app.name'),
                    'brandTagline' => __('notifications.common.brand_tagline'),
                ]);

            if ($this->mailLevel() === 'error') {
                $mail->error();
            } elseif ($this->mailLevel() === 'success') {
                $mail->success();
            }

            return $mail;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->withLocale($notifiable, function (object $notifiable): array {
            $content = $this->resolvedContent($notifiable, CommunicationChannelEnum::DATABASE);

            return [
                'topic' => $this->topicKey(),
                'topic_label' => $this->translate('topic'),
                'title' => $content['title'],
                'message' => $content['body'],
                'payload' => $this->payloadForDatabase($notifiable),
            ];
        });
    }

    abstract protected function topicKey(): string;

    abstract protected function mailMarkdownView(): string;

    protected function mailLevel(): string
    {
        return 'info';
    }

    protected function title(object $notifiable): string
    {
        return $this->translate('title');
    }

    protected function message(object $notifiable): string
    {
        return $this->translate('message');
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function details(object $notifiable): array
    {
        return [];
    }

    protected function actionLabel(object $notifiable): ?string
    {
        return null;
    }

    protected function actionUrl(object $notifiable): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadForDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * @return array{subject: string, title: string, body: string, cta_label: ?string, cta_url: ?string}
     */
    protected function resolvedContent(object $notifiable, CommunicationChannelEnum $channel): array
    {
        $fallback = [
            'subject' => $this->translate('subject'),
            'title' => $this->title($notifiable),
            'body' => $this->message($notifiable),
            'cta_label' => $this->actionLabel($notifiable),
            'cta_url' => $this->actionUrl($notifiable),
        ];

        $payload = $this->payloadForTemplate($notifiable);

        try {
            $resolved = app(CommunicationTemplateResolver::class)->resolveForTopic(
                $this->topicKey(),
                $channel,
            );
        } catch (\Throwable) {
            if ($channel !== CommunicationChannelEnum::MAIL) {
                try {
                    $resolved = app(CommunicationTemplateResolver::class)->resolveForTopic(
                        $this->topicKey(),
                        CommunicationChannelEnum::MAIL,
                    );
                } catch (\Throwable) {
                    return $fallback;
                }
            } else {
                return $fallback;
            }
        }

        $rendered = app(CommunicationTemplateRenderer::class)->render($resolved, $payload);

        return [
            'subject' => $rendered['subject'] ?? $fallback['subject'],
            'title' => $rendered['title'] ?? $fallback['title'],
            'body' => $rendered['body'] ?? $fallback['body'],
            'cta_label' => $rendered['cta_label'] ?? $fallback['cta_label'],
            'cta_url' => $rendered['cta_url'] ?? $fallback['cta_url'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadForTemplate(object $notifiable): array
    {
        return array_merge($this->payloadForDatabase($notifiable), [
            'action_url' => $this->actionUrl($notifiable),
        ]);
    }

    protected function translate(string $suffix, array $replace = []): string
    {
        return __("notifications.topics.{$this->topicKey()}.{$suffix}", $replace);
    }

    protected function stringValue(mixed $value, string $fallback = '-'): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $fallback;
    }

    protected function formatStructuredValue(mixed $value): string
    {
        if (is_array($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($json) ? $json : '-';
        }

        return $this->stringValue($value);
    }

    protected function withLocale(object $notifiable, callable $callback): mixed
    {
        $previousLocale = App::currentLocale();
        $locale = method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : $previousLocale;

        App::setLocale($locale ?: $previousLocale);

        try {
            return $callback($notifiable);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
