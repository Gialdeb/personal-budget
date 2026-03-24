<?php

namespace App\Services\Communication;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\NotificationTopic;
use App\Models\User;

class NotificationPreferenceResolver
{
    /**
     * @return array<int, NotificationChannelEnum>
     */
    public function resolveChannels(User $user, NotificationTopic $topic): array
    {
        if (! $topic->is_active) {
            return [];
        }

        return match ($topic->preference_mode) {
            NotificationPreferenceModeEnum::MANDATORY => $this->resolveMandatoryChannels($topic),
            NotificationPreferenceModeEnum::SYSTEM => $this->resolveSystemChannels($topic),
            NotificationPreferenceModeEnum::USER_CONFIGURABLE,
            NotificationPreferenceModeEnum::ADMIN_CONFIGURABLE => $this->resolveConfigurableChannels($user, $topic),
        };
    }

    /**
     * @return array<int, NotificationChannelEnum>
     */
    protected function resolveMandatoryChannels(NotificationTopic $topic): array
    {
        $channels = [];

        if ($topic->supports_email && $topic->default_email_enabled) {
            $channels[] = NotificationChannelEnum::EMAIL;
        }

        if ($topic->supports_in_app && $topic->default_in_app_enabled) {
            $channels[] = NotificationChannelEnum::IN_APP;
        }

        if ($topic->supports_sms && $topic->default_sms_enabled) {
            $channels[] = NotificationChannelEnum::SMS;
        }

        return $channels;
    }

    /**
     * @return array<int, NotificationChannelEnum>
     */
    protected function resolveSystemChannels(NotificationTopic $topic): array
    {
        return $this->resolveMandatoryChannels($topic);
    }

    /**
     * @return array<int, NotificationChannelEnum>
     */
    protected function resolveConfigurableChannels(User $user, NotificationTopic $topic): array
    {
        $preference = $user->notificationPreferences()
            ->where('notification_topic_id', $topic->id)
            ->first();

        $channels = [];

        $emailEnabled = $preference?->email_enabled ?? $topic->default_email_enabled;
        $inAppEnabled = $preference?->in_app_enabled ?? $topic->default_in_app_enabled;
        $smsEnabled = $preference?->sms_enabled ?? $topic->default_sms_enabled;

        if ($topic->supports_email && $emailEnabled) {
            $channels[] = NotificationChannelEnum::EMAIL;
        }

        if ($topic->supports_in_app && $inAppEnabled) {
            $channels[] = NotificationChannelEnum::IN_APP;
        }

        if ($topic->supports_sms && $smsEnabled) {
            $channels[] = NotificationChannelEnum::SMS;
        }

        return $channels;
    }

    public function allowsAnyChannel(User $user, NotificationTopic $topic): bool
    {
        return $this->resolveChannels($user, $topic) !== [];
    }
}
