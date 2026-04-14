<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\CommunicationCategory;
use Illuminate\Database\Eloquent\Builder;

class CommunicationPreferenceCatalog
{
    /**
     * @return array<string, string>
     */
    public function categoryTopicMap(): array
    {
        $map = [
            'credit_cards.autopay_completed' => 'credit_card_autopay_completed',
            'recurring.weekly_due_summary' => 'recurring_weekly_due_summary',
            'recurring.monthly_due_summary' => 'recurring_monthly_due_summary',
        ];

        if (config('features.imports.enabled')) {
            $map['imports.completed'] = 'import_completed';
        }

        return $map;
    }

    public function topicKeyForCategory(string $categoryKey): ?string
    {
        return $this->categoryTopicMap()[$categoryKey] ?? null;
    }

    public function configurableCategoriesQuery(): Builder
    {
        return CommunicationCategory::query()
            ->where('is_active', true)
            ->where('preference_mode', NotificationPreferenceModeEnum::USER_CONFIGURABLE->value)
            ->whereIn('audience', [
                NotificationAudienceEnum::USER->value,
                NotificationAudienceEnum::BOTH->value,
            ])
            ->whereHas('channelTemplates', function (Builder $query): void {
                $query
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->whereIn('channel', [
                        CommunicationChannelEnum::MAIL->value,
                        CommunicationChannelEnum::DATABASE->value,
                    ]);
            });
    }

    /**
     * @return array{email: bool, in_app: bool}
     */
    public function availableChannels(CommunicationCategory $category): array
    {
        $channels = $category->channelTemplates
            ->where('is_active', true)
            ->where('is_default', true)
            ->pluck('channel')
            ->map(fn ($channel) => $channel instanceof CommunicationChannelEnum ? $channel->value : (string) $channel)
            ->all();

        return [
            'email' => in_array(CommunicationChannelEnum::MAIL->value, $channels, true),
            'in_app' => in_array(CommunicationChannelEnum::DATABASE->value, $channels, true),
        ];
    }
}
