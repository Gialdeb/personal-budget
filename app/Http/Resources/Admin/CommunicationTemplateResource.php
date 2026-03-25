<?php

namespace App\Http\Resources\Admin;

use App\Enums\CommunicationTemplateModeEnum;
use App\Models\CommunicationTemplateOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CommunicationTemplateOverride|null $latestOverride */
        $latestOverride = $this->overrides->first();
        $topicKey = $this->notificationTopic?->key;
        $canEditOverride = ! $this->is_system_locked && $this->template_mode !== CommunicationTemplateModeEnum::SYSTEM;

        return [
            'uuid' => $this->uuid,
            'key' => $this->key,
            'name' => $this->translatedValue("admin.communication_templates.templates.{$this->key}.name", $this->name),
            'description' => $this->translatedValue(
                "admin.communication_templates.templates.{$this->key}.description",
                $this->description,
            ),
            'channel' => $this->channel?->value,
            'channel_label' => $this->translatedValue("admin.communication_templates.channels.{$this->channel?->value}"),
            'template_mode' => $this->template_mode?->value,
            'template_mode_label' => $this->translatedValue("admin.communication_templates.modes.{$this->template_mode?->value}"),
            'is_system_locked' => (bool) $this->is_system_locked,
            'is_active' => (bool) $this->is_active,
            'topic' => $this->notificationTopic ? [
                'uuid' => $this->notificationTopic->uuid,
                'key' => $topicKey,
                'label' => $this->translatedValue("notifications.topics.{$topicKey}.topic", $this->notificationTopic->name),
            ] : null,
            'override' => [
                'exists' => $latestOverride instanceof CommunicationTemplateOverride,
                'uuid' => $latestOverride?->uuid,
                'is_active' => (bool) $latestOverride?->is_active,
                'subject_template' => $latestOverride?->subject_template,
                'title_template' => $latestOverride?->title_template,
                'body_template' => $latestOverride?->body_template,
                'cta_label_template' => $latestOverride?->cta_label_template,
                'cta_url_template' => $latestOverride?->cta_url_template,
            ],
            'flags' => [
                'can_edit_override' => $canEditOverride,
                'can_disable_override' => $canEditOverride && $latestOverride instanceof CommunicationTemplateOverride,
            ],
        ];
    }

    protected function translatedValue(string $key, ?string $fallback = null): string
    {
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $fallback ?? $key;
    }
}
