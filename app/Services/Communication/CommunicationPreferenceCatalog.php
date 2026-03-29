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
        return [
            'credit_cards.autopay_completed' => 'credit_card_autopay_completed',
            'imports.completed' => 'import_completed',
            'reports.weekly_ready' => 'monthly_report_ready',
        ];
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
