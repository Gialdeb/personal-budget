<?php

namespace App\Services\Communication;

use App\Data\Communication\ComposedCommunicationData;
use App\Enums\CommunicationChannelEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Jobs\DeliverOutboundMessageJob;
use App\Models\CommunicationCategory;
use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CommunicationDispatchService
{
    public function __construct(
        protected CommunicationComposerService $composerService,
        protected NotificationPreferenceResolver $notificationPreferenceResolver,
        protected CommunicationPreferenceCatalog $preferenceCatalog,
        protected CommunicationCategoryChannelService $categoryChannelService,
    ) {}

    /**
     * @return array<int, OutboundMessage>
     */
    public function dispatchForUserCategory(
        string $categoryKey,
        User $recipient,
        object $contextModel,
        ?User $actor = null,
    ): array {
        $category = CommunicationCategory::query()
            ->where('key', $categoryKey)
            ->where('is_active', true)
            ->with(['channelTemplates' => fn ($query) => $query->with('template')])
            ->first();

        if (! $category) {
            throw new InvalidArgumentException("Communication category [{$categoryKey}] does not exist or is not active.");
        }

        $channels = $this->resolveChannels($category, $recipient);

        $messages = [];

        foreach ($channels as $channel) {
            $composed = $this->composerService->compose($categoryKey, $channel, $contextModel);

            $messages[] = $this->createQueuedMessage(
                composed: $composed,
                recipient: $recipient,
                contextModel: $contextModel,
                actor: $actor,
            );
        }

        if ($messages === []) {
            $messages[] = $this->createSkippedMessage(
                category: $category,
                recipient: $recipient,
                contextModel: $contextModel,
                actor: $actor,
            );
        }

        return $messages;
    }

    public function dispatchManualCategory(
        string $categoryKey,
        CommunicationChannelEnum $channel,
        User $recipient,
        object $contextModel,
        ?User $actor = null,
        ?string $forcedLocale = null,
        ?array $contentOverrides = null,
    ): OutboundMessage {
        $category = CommunicationCategory::query()
            ->where('key', $categoryKey)
            ->where('is_active', true)
            ->with(['channelTemplates' => fn ($query) => $query->with('template')])
            ->first();

        if (! $category) {
            throw new InvalidArgumentException("Communication category [{$categoryKey}] does not exist or is not active.");
        }

        $composed = $this->composerService->compose(
            $categoryKey,
            $channel,
            $contextModel,
            $forcedLocale,
            $contentOverrides,
        );

        return $this->createQueuedMessage(
            composed: $composed,
            recipient: $recipient,
            contextModel: $contextModel,
            actor: $actor,
        );
    }

    public function dispatchManualCategoryToMailAddress(
        string $categoryKey,
        string $email,
        object $contextModel,
        ?string $recipientLabel = null,
        ?User $actor = null,
        ?string $forcedLocale = null,
        ?array $contentOverrides = null,
    ): OutboundMessage {
        $category = CommunicationCategory::query()
            ->where('key', $categoryKey)
            ->where('is_active', true)
            ->with(['channelTemplates' => fn ($query) => $query->with('template')])
            ->first();

        if (! $category) {
            throw new InvalidArgumentException("Communication category [{$categoryKey}] does not exist or is not active.");
        }

        $composed = $this->composerService->compose(
            $categoryKey,
            CommunicationChannelEnum::MAIL,
            $contextModel,
            $forcedLocale,
            $contentOverrides,
        );

        return $this->createQueuedMailAddressMessage(
            composed: $composed,
            email: $email,
            recipientLabel: $recipientLabel,
            contextModel: $contextModel,
            actor: $actor,
        );
    }

    /**
     * @param  array<int, CommunicationChannelEnum>  $channels
     * @param  array<int, User>  $recipients
     * @param  array{subject?: ?string, title?: ?string, body?: ?string, cta_label?: ?string, cta_url?: ?string}|null  $contentOverrides
     * @return array<int, OutboundMessage>
     */
    public function dispatchManualBatch(
        string $categoryKey,
        array $channels,
        array $recipients,
        ?User $actor = null,
        ?string $forcedLocale = null,
        ?array $contentOverrides = null,
    ): array {
        $messages = [];

        foreach ($recipients as $recipient) {
            foreach ($channels as $channel) {
                $messages[] = $this->dispatchManualCategory(
                    categoryKey: $categoryKey,
                    channel: $channel,
                    recipient: $recipient,
                    contextModel: $recipient,
                    actor: $actor,
                    forcedLocale: $forcedLocale,
                    contentOverrides: $contentOverrides,
                );
            }
        }

        return $messages;
    }

    /**
     * @return array<int, CommunicationChannelEnum>
     */
    protected function resolveChannels(CommunicationCategory $category, User $recipient): array
    {
        if ($category->preference_mode === NotificationPreferenceModeEnum::MANDATORY) {
            return $this->defaultChannelsForCategory($category);
        }

        if ($category->preference_mode === NotificationPreferenceModeEnum::USER_CONFIGURABLE) {
            $topic = $this->topicForCategory($category);

            if (! $topic) {
                return $this->defaultChannelsForCategory($category);
            }

            return collect($this->notificationPreferenceResolver->resolveChannels($recipient, $topic))
                ->map(function ($channel) {
                    $value = $channel instanceof NotificationChannelEnum
                        ? $channel->value
                        : (string) $channel;

                    return match ($value) {
                        NotificationChannelEnum::EMAIL->value => CommunicationChannelEnum::MAIL,
                        NotificationChannelEnum::IN_APP->value => CommunicationChannelEnum::DATABASE,
                        default => null,
                    };
                })
                ->filter()
                ->filter(fn (CommunicationChannelEnum $channel) => in_array(
                    $channel->value,
                    array_map(
                        static fn (CommunicationChannelEnum $allowedChannel): string => $allowedChannel->value,
                        $this->defaultChannelsForCategory($category),
                    ),
                    true,
                ))
                ->values()
                ->all();
        }

        return $this->defaultChannelsForCategory($category);
    }

    /**
     * @return array<int, CommunicationChannelEnum>
     */
    protected function defaultChannelsForCategory(CommunicationCategory $category): array
    {
        return $this->categoryChannelService->activeDefaultChannels($category);
    }

    protected function topicForCategory(CommunicationCategory $category): ?NotificationTopic
    {
        $topicKey = $this->preferenceCatalog->topicKeyForCategory($category->key);

        if (! $topicKey) {
            return null;
        }

        return NotificationTopic::query()
            ->where('key', $topicKey)
            ->where('is_active', true)
            ->first();
    }

    protected function createQueuedMessage(
        ComposedCommunicationData $composed,
        User $recipient,
        object $contextModel,
        ?User $actor = null,
    ): OutboundMessage {
        $message = OutboundMessage::query()->create([
            'communication_category_id' => $composed->category->id,
            'communication_template_id' => $composed->template->id,
            'channel' => $composed->channel,
            'status' => OutboundMessageStatusEnum::QUEUED,
            'recipient_type' => $recipient->getMorphClass(),
            'recipient_id' => $recipient->getKey(),
            'context_type' => $contextModel->getMorphClass(),
            'context_id' => $contextModel->getKey(),
            'subject_resolved' => $composed->subject,
            'title_resolved' => $composed->title,
            'body_resolved' => $composed->body,
            'cta_label_resolved' => $composed->ctaLabel,
            'cta_url_resolved' => $composed->ctaUrl,
            'payload_snapshot' => $composed->toArray(),
            'queued_at' => $this->now(),
            'created_by' => $actor?->id,
        ]);

        DeliverOutboundMessageJob::dispatch($message->id);

        return $message->fresh();

    }

    protected function createQueuedMailAddressMessage(
        ComposedCommunicationData $composed,
        string $email,
        ?string $recipientLabel,
        object $contextModel,
        ?User $actor = null,
    ): OutboundMessage {
        $payloadSnapshot = $composed->toArray();
        $payloadSnapshot['recipient'] = [
            'email' => $email,
            'label' => $recipientLabel ?: $email,
            'type' => 'email',
        ];

        $message = OutboundMessage::query()->create([
            'communication_category_id' => $composed->category->id,
            'communication_template_id' => $composed->template->id,
            'channel' => $composed->channel,
            'status' => OutboundMessageStatusEnum::QUEUED,
            'recipient_type' => $contextModel->getMorphClass(),
            'recipient_id' => $contextModel->getKey(),
            'context_type' => $contextModel->getMorphClass(),
            'context_id' => $contextModel->getKey(),
            'subject_resolved' => $composed->subject,
            'title_resolved' => $composed->title,
            'body_resolved' => $composed->body,
            'cta_label_resolved' => $composed->ctaLabel,
            'cta_url_resolved' => $composed->ctaUrl,
            'payload_snapshot' => $payloadSnapshot,
            'queued_at' => $this->now(),
            'created_by' => $actor?->id,
        ]);

        DeliverOutboundMessageJob::dispatch($message->id);

        return $message->fresh();
    }

    protected function createSkippedMessage(
        CommunicationCategory $category,
        User $recipient,
        object $contextModel,
        ?User $actor = null,
    ): OutboundMessage {
        $message = OutboundMessage::query()->create([
            'communication_category_id' => $category->id,
            'communication_template_id' => null,
            'channel' => CommunicationChannelEnum::MAIL,
            'status' => OutboundMessageStatusEnum::SKIPPED,
            'recipient_type' => $recipient->getMorphClass(),
            'recipient_id' => $recipient->getKey(),
            'context_type' => $contextModel->getMorphClass(),
            'context_id' => $contextModel->getKey(),
            'subject_resolved' => null,
            'title_resolved' => null,
            'body_resolved' => 'Communication skipped due to channel preferences or missing channel mapping.',
            'cta_label_resolved' => null,
            'cta_url_resolved' => null,
            'payload_snapshot' => [
                'category_key' => $category->key,
                'reason' => 'skipped',
            ],
            'created_by' => $actor?->id,
        ]);

        DeliverOutboundMessageJob::dispatch($message->id);

        return $message->fresh();
    }

    protected function now(): CarbonInterface
    {
        return Carbon::now();
    }
}
