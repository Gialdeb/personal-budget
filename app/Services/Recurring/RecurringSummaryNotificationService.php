<?php

namespace App\Services\Recurring;

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Models\CommunicationCategory;
use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\RecurringEntry;
use App\Models\User;
use App\Services\Communication\CommunicationDispatchService;
use App\Services\Communication\NotificationPreferenceResolver;
use Carbon\CarbonImmutable;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class RecurringSummaryNotificationService
{
    protected const MONTHLY_WINDOW_DAYS = 10;

    public function __construct(
        protected RecurringEntryOccurrenceGeneratorService $occurrenceGenerator,
        protected CommunicationDispatchService $communicationDispatchService,
        protected NotificationPreferenceResolver $notificationPreferenceResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sendWeeklySummary(?CarbonImmutable $referenceDate = null): array
    {
        $referenceDate ??= CarbonImmutable::today();
        $windowStart = $referenceDate->startOfWeek();
        $windowEnd = $windowStart->addDays(6);

        return $this->sendSummaryForWindow(
            categoryKey: 'recurring.weekly_due_summary',
            summaryKey: 'weekly',
            windowStart: $windowStart,
            windowEnd: $windowEnd,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMonthlySummary(?CarbonImmutable $referenceDate = null): array
    {
        $referenceDate ??= CarbonImmutable::today();
        $windowStart = $referenceDate->startOfMonth();
        $windowEnd = $windowStart->addDays(self::MONTHLY_WINDOW_DAYS - 1);

        return $this->sendSummaryForWindow(
            categoryKey: 'recurring.monthly_due_summary',
            summaryKey: 'monthly',
            windowStart: $windowStart,
            windowEnd: $windowEnd,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendSummaryForWindow(
        string $categoryKey,
        string $summaryKey,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): array {
        $this->ensureCommunicationDefinitions();

        $category = CommunicationCategory::query()
            ->where('key', $categoryKey)
            ->where('is_active', true)
            ->firstOrFail();
        $topic = NotificationTopic::query()
            ->where('key', $this->topicKeyForCategory($categoryKey))
            ->where('is_active', true)
            ->firstOrFail();

        $users = User::query()
            ->whereHas('notificationPreferences', function ($query) use ($topic): void {
                $query
                    ->where('notification_topic_id', $topic->id)
                    ->where('email_enabled', true);
            })
            ->get();

        $result = [
            'summary_key' => $summaryKey,
            'window_start' => $windowStart->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'processed_count' => $users->count(),
            'success_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'skipped_count' => 0,
            'delivered_count' => 0,
            'user_results' => [],
        ];

        foreach ($users as $user) {
            $items = $this->summaryItemsForUser($user, $windowStart, $windowEnd);

            if ($items->isEmpty()) {
                $result['skipped_count']++;
                $result['user_results'][] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'skipped',
                    'detail' => 'No recurring entries due in the target window.',
                ];

                continue;
            }

            if ($this->alreadySent($category, $user, $summaryKey, $windowStart, $windowEnd)) {
                $result['warning_count']++;
                $result['user_results'][] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'already_sent',
                    'detail' => 'Summary already queued or delivered for this window.',
                ];

                continue;
            }

            $messages = $this->dispatchSummary($categoryKey, $topic, $summaryKey, $user, $windowStart, $windowEnd, $items);

            $result['success_count']++;
            $result['delivered_count'] += count($messages);
            $result['user_results'][] = [
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => 'queued',
                'items_count' => $items->count(),
                'outbound_uuids' => array_map(
                    static fn (OutboundMessage $message): string => $message->uuid,
                    $messages,
                ),
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function summaryItemsForUser(
        User $user,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): Collection {
        $entries = RecurringEntry::query()
            ->with(['merchant:id,name', 'trackedItem:id,name', 'category:id,name,name_is_custom,slug,foundation_key'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $windowEnd->toDateString())
            ->get();

        return $entries
            ->flatMap(function (RecurringEntry $entry) use ($windowStart, $windowEnd): Collection {
                return $this->occurrenceGenerator
                    ->previewDatesWithinRange($entry, $windowStart, $windowEnd, 64)
                    ->map(function (CarbonImmutable $date) use ($entry): array {
                        return [
                            'date' => $date,
                            'title' => $this->entryLabel($entry),
                            'amount' => (float) ($entry->expected_amount ?? $entry->total_amount ?? 0),
                            'currency' => $entry->currency ?? 'EUR',
                        ];
                    });
            })
            ->sortBy(fn (array $item): string => $item['date']->toDateString())
            ->values();
    }

    protected function dispatchSummary(
        string $categoryKey,
        NotificationTopic $topic,
        string $summaryKey,
        User $user,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        Collection $items,
    ): array {
        $locale = $user->preferredLocale();
        $previousLocale = App::getLocale();
        App::setLocale($locale);

        try {
            $windowLabel = __('notifications.recurring_summaries.windows.'.$summaryKey, [
                'start' => $this->formatDateForLocale($windowStart),
                'end' => $this->formatDateForLocale($windowEnd),
            ]);

            $body = __('notifications.recurring_summaries.intro', [
                'window' => $windowLabel,
            ])."\n\n".$items
                ->map(fn (array $item): string => __('notifications.recurring_summaries.item', [
                    'date' => $this->formatDateForLocale($item['date']),
                    'title' => $item['title'],
                    'amount' => $this->formatAmount($item['amount'], $item['currency']),
                ]))
                ->implode("\n");

            $messages = [];

            foreach ($this->enabledCommunicationChannels($user, $topic) as $channel) {
                $messages[] = $this->communicationDispatchService->dispatchManualCategory(
                    categoryKey: $categoryKey,
                    channel: $channel,
                    recipient: $user,
                    contextModel: $user,
                    forcedLocale: $locale,
                    contentOverrides: [
                        'body' => $body,
                    ],
                );
            }
        } finally {
            App::setLocale($previousLocale);
        }

        return array_map(function (OutboundMessage $message) use ($summaryKey, $windowStart, $windowEnd, $items): OutboundMessage {
            $payloadSnapshot = is_array($message->payload_snapshot) ? $message->payload_snapshot : [];
            $payloadSnapshot['summary_key'] = $summaryKey;
            $payloadSnapshot['window_start'] = $windowStart->toDateString();
            $payloadSnapshot['window_end'] = $windowEnd->toDateString();
            $payloadSnapshot['items_count'] = $items->count();

            $message->forceFill([
                'payload_snapshot' => $payloadSnapshot,
            ])->save();

            return $message->fresh();
        }, $messages);
    }

    /**
     * @return array<int, CommunicationChannelEnum>
     */
    protected function enabledCommunicationChannels(User $user, NotificationTopic $topic): array
    {
        return collect($this->notificationPreferenceResolver->resolveChannels($user, $topic))
            ->map(fn ($channel): ?CommunicationChannelEnum => CommunicationChannelEnum::tryFrom($channel->value))
            ->filter()
            ->values()
            ->all();
    }

    protected function alreadySent(
        CommunicationCategory $category,
        User $user,
        string $summaryKey,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): bool {
        return OutboundMessage::query()
            ->where('communication_category_id', $category->id)
            ->where('recipient_type', $user->getMorphClass())
            ->where('recipient_id', $user->id)
            ->where('channel', CommunicationChannelEnum::MAIL->value)
            ->whereIn('status', [
                OutboundMessageStatusEnum::QUEUED->value,
                OutboundMessageStatusEnum::SENT->value,
            ])
            ->whereJsonContains('payload_snapshot->summary_key', $summaryKey)
            ->whereJsonContains('payload_snapshot->window_start', $windowStart->toDateString())
            ->whereJsonContains('payload_snapshot->window_end', $windowEnd->toDateString())
            ->exists();
    }

    protected function topicKeyForCategory(string $categoryKey): string
    {
        return [
            'recurring.weekly_due_summary' => 'recurring_weekly_due_summary',
            'recurring.monthly_due_summary' => 'recurring_monthly_due_summary',
        ][$categoryKey];
    }

    protected function entryLabel(RecurringEntry $entry): string
    {
        $label = $entry->title
            ?: $entry->trackedItem?->name
            ?: $entry->merchant?->name
            ?: $entry->category?->displayName();

        return $label !== null && $label !== ''
            ? $label
            : __('notifications.recurring_summaries.fallback_label');
    }

    protected function formatDateForLocale(CarbonImmutable $date): string
    {
        return $date
            ->locale(App::currentLocale())
            ->translatedFormat('j M');
    }

    protected function formatAmount(float $amount, string $currency): string
    {
        return sprintf('%s %.2f', strtoupper($currency), $amount);
    }

    protected function ensureCommunicationDefinitions(): void
    {
        app(NotificationTopicSeeder::class)->run();
        app(CommunicationTemplateSeeder::class)->run();
        app(CommunicationCategorySeeder::class)->run();
    }
}
