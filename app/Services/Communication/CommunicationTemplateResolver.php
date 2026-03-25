<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use App\Enums\CommunicationTemplateOverrideScopeEnum;
use App\Models\CommunicationTemplate;
use App\Models\NotificationTopic;
use InvalidArgumentException;

class CommunicationTemplateResolver
{
    public function resolveForTopic(
        string $topicKey,
        CommunicationChannelEnum $channel = CommunicationChannelEnum::MAIL,
    ): array {
        $topic = NotificationTopic::query()
            ->where('key', $topicKey)
            ->where('is_active', true)
            ->first();

        if (! $topic) {
            throw new InvalidArgumentException("Notification topic [{$topicKey}] does not exist or is not active.");
        }

        $template = CommunicationTemplate::query()
            ->where('notification_topic_id', $topic->id)
            ->where('channel', $channel->value)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            throw new InvalidArgumentException("Communication template for topic [{$topicKey}] and channel [{$channel->value}] was not found.");
        }

        return $this->resolveTemplate($template);
    }

    public function resolveByTemplateKey(string $templateKey): array
    {
        $template = CommunicationTemplate::query()
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            throw new InvalidArgumentException("Communication template [{$templateKey}] was not found or is not active.");
        }

        return $this->resolveTemplate($template);
    }

    protected function resolveTemplate(CommunicationTemplate $template): array
    {
        $override = null;

        if (! $template->is_system_locked && $template->template_mode !== CommunicationTemplateModeEnum::SYSTEM) {
            $override = $template->overrides()
                ->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value)
                ->where('is_active', true)
                ->latest('id')
                ->first();
        }

        return [
            'template' => $template,
            'override' => $override,
            'subject_template' => $override?->subject_template ?? $template->subject_template,
            'title_template' => $override?->title_template ?? $template->title_template,
            'body_template' => $override?->body_template ?? $template->body_template,
            'cta_label_template' => $override?->cta_label_template ?? $template->cta_label_template,
            'cta_url_template' => $override?->cta_url_template ?? $template->cta_url_template,
        ];
    }
}
