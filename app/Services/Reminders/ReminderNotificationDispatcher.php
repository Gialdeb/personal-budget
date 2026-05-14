<?php

namespace App\Services\Reminders;

use App\Enums\CommunicationChannelEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\ReminderDelivery;
use App\Models\User;
use App\Services\Communication\NotificationPreferenceResolver;
use App\Services\Communication\OutboundMessageDeliveryService;
use App\Services\Push\DeviceTokenService;
use App\Services\Push\PushNotificationService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReminderNotificationDispatcher
{
    public function __construct(
        protected NotificationPreferenceResolver $notificationPreferenceResolver,
        protected OutboundMessageDeliveryService $outboundMessageDeliveryService,
        protected DeviceTokenService $deviceTokenService,
        protected PushNotificationService $pushNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{status: string, pushed: int}
     */
    public function dispatch(
        User $user,
        Model $remindable,
        string $topicKey,
        string $categoryKey,
        string $templateKey,
        string $notificationKind,
        string $reminderType,
        Carbon $dueDate,
        string $title,
        string $body,
        string $targetUrl,
        string $severity,
        array $metadata,
    ): array {
        $this->ensureCommunicationDefinitions();

        $topic = NotificationTopic::query()->where('key', $topicKey)->firstOrFail();
        $channels = $this->notificationPreferenceResolver->resolveChannels($user, $topic);

        if (! in_array(NotificationChannelEnum::IN_APP, $channels, true)) {
            return ['status' => 'skipped', 'pushed' => 0];
        }

        $deliveryDate = $this->deliveryDate($dueDate, $reminderType);

        return DB::transaction(function () use (
            $user,
            $remindable,
            $categoryKey,
            $templateKey,
            $notificationKind,
            $reminderType,
            $dueDate,
            $deliveryDate,
            $title,
            $body,
            $targetUrl,
            $severity,
            $metadata,
        ): array {
            $deliveryAttributes = [
                'user_id' => $user->id,
                'remindable_type' => $remindable->getMorphClass(),
                'remindable_id' => $remindable->getKey(),
                'reminder_type' => $reminderType,
                'due_date' => $dueDate->toDateString(),
                'delivery_date' => $deliveryDate->toDateString(),
            ];

            $delivery = ReminderDelivery::query()
                ->where($deliveryAttributes)
                ->first();

            if ($delivery instanceof ReminderDelivery) {
                return ['status' => 'duplicate', 'pushed' => 0];
            }

            try {
                $delivery = ReminderDelivery::query()->create([
                    ...$deliveryAttributes,
                    'notification_kind' => $notificationKind,
                ]);
            } catch (QueryException) {
                return ['status' => 'duplicate', 'pushed' => 0];
            }

            $message = $this->createInAppMessage(
                $user,
                $remindable,
                $categoryKey,
                $templateKey,
                $title,
                $body,
                $targetUrl,
                $severity,
                $metadata,
            );

            $this->outboundMessageDeliveryService->deliver($message);

            $pushed = $this->pushIfAllowed($user, $title, $body, $targetUrl, $metadata);

            $delivery->forceFill([
                'outbound_message_id' => $message->id,
                'pushed_at' => $pushed > 0 ? Carbon::now() : null,
            ])->save();

            return ['status' => 'notified', 'pushed' => $pushed];
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function createInAppMessage(
        User $user,
        Model $remindable,
        string $categoryKey,
        string $templateKey,
        string $title,
        string $body,
        string $targetUrl,
        string $severity,
        array $metadata,
    ): OutboundMessage {
        $category = CommunicationCategory::query()->where('key', $categoryKey)->firstOrFail();
        $template = CommunicationTemplate::query()->where('key', $templateKey)->first();

        return OutboundMessage::query()->create([
            'communication_category_id' => $category->id,
            'communication_template_id' => $template?->id,
            'channel' => CommunicationChannelEnum::DATABASE,
            'status' => OutboundMessageStatusEnum::QUEUED,
            'recipient_type' => $user->getMorphClass(),
            'recipient_id' => $user->id,
            'context_type' => $remindable->getMorphClass(),
            'context_id' => $remindable->getKey(),
            'subject_resolved' => null,
            'title_resolved' => $title,
            'body_resolved' => $body,
            'cta_label_resolved' => __('notifications.reminders.cta.open'),
            'cta_url_resolved' => $targetUrl,
            'payload_snapshot' => [
                'severity' => $severity,
                ...$metadata,
            ],
            'queued_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function pushIfAllowed(User $user, string $title, string $body, string $targetUrl, array $metadata): int
    {
        if (! config('features.push_notifications.enabled') || ! $user->pushNotificationsEnabled()) {
            return 0;
        }

        if ($this->deviceTokenService->activeBroadcastTokensForUser($user)->isEmpty()) {
            return 0;
        }

        try {
            $summary = $this->pushNotificationService->sendToUser(
                $user,
                $title,
                $body,
                $targetUrl,
                $metadata,
            );

            return (int) $summary['sent_count'];
        } catch (Throwable) {
            return 0;
        }
    }

    protected function deliveryDate(Carbon $dueDate, string $reminderType): Carbon
    {
        if (! str_ends_with($reminderType, '_overdue')) {
            return $dueDate->copy();
        }

        if (! (bool) config('reminders.overdue_repeat_daily', true)) {
            return $dueDate->copy();
        }

        return Carbon::now(config('app.timezone'))->startOfDay();
    }

    public function ensureCommunicationDefinitions(): void
    {
        if (
            NotificationTopic::query()->where('key', 'recurring_due_reminders')->exists()
            && NotificationTopic::query()->where('key', 'credits_debts_due_reminders')->exists()
            && CommunicationCategory::query()->where('key', 'reminders.recurring_due')->exists()
            && CommunicationCategory::query()->where('key', 'reminders.credits_debts_due')->exists()
        ) {
            return;
        }

        App::make(NotificationTopicSeeder::class)->run();
        App::make(CommunicationTemplateSeeder::class)->run();
        App::make(CommunicationCategorySeeder::class)->run();
    }
}
