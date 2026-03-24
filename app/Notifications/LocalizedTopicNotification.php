<?php

namespace App\Notifications;

use App\Models\NotificationTopic;
use App\Models\User;
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
            $mail = (new MailMessage)
                ->subject($this->translate('subject'))
                ->markdown($this->mailMarkdownView(), [
                    'title' => $this->title($notifiable),
                    'message' => $this->message($notifiable),
                    'details' => $this->details($notifiable),
                    'detailsTitle' => __('notifications.common.details'),
                    'actionLabel' => $this->actionLabel($notifiable),
                    'actionUrl' => $this->actionUrl($notifiable),
                    'footer' => __('notifications.common.footer', ['app' => config('app.name')]),
                    'appName' => config('app.name'),
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
        return $this->withLocale($notifiable, fn (object $notifiable): array => [
            'topic' => $this->topicKey(),
            'topic_label' => $this->translate('topic'),
            'title' => $this->title($notifiable),
            'message' => $this->message($notifiable),
            'payload' => $this->payloadForDatabase($notifiable),
        ]);
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
