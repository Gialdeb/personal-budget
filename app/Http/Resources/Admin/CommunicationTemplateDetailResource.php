<?php

namespace App\Http\Resources\Admin;

use App\Enums\CommunicationTemplateModeEnum;
use App\Models\CommunicationTemplateOverride;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\CommunicationTemplateResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationTemplateDetailResource extends JsonResource
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
        $resolved = app(CommunicationTemplateResolver::class)->resolveByTemplateKey($this->key);
        $preview = app(CommunicationTemplateRenderer::class)->render($resolved, []);
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
            'base_template' => [
                'subject_template' => $this->subject_template,
                'title_template' => $this->title_template,
                'body_template' => $this->body_template,
                'cta_label_template' => $this->cta_label_template,
                'cta_url_template' => $this->cta_url_template,
            ],
            'global_override' => $latestOverride ? [
                'uuid' => $latestOverride->uuid,
                'scope' => $latestOverride->scope?->value,
                'is_active' => (bool) $latestOverride->is_active,
                'subject_template' => $latestOverride->subject_template,
                'title_template' => $latestOverride->title_template,
                'body_template' => $latestOverride->body_template,
                'cta_label_template' => $latestOverride->cta_label_template,
                'cta_url_template' => $latestOverride->cta_url_template,
            ] : null,
            'resolved_content' => [
                'subject_template' => $resolved['subject_template'] ?? null,
                'title_template' => $resolved['title_template'] ?? null,
                'body_template' => $resolved['body_template'] ?? null,
                'cta_label_template' => $resolved['cta_label_template'] ?? null,
                'cta_url_template' => $resolved['cta_url_template'] ?? null,
            ],
            'preview' => [
                'subject' => $preview['subject'] ?? null,
                'title' => $preview['title'] ?? null,
                'body' => $preview['body'] ?? null,
                'cta_label' => $preview['cta_label'] ?? null,
                'cta_url' => $preview['cta_url'] ?? null,
            ],
            'available_variables' => $this->availableVariables([
                $this->translatedTemplateValue($this->subject_template),
                $this->translatedTemplateValue($this->title_template),
                $this->translatedTemplateValue($this->body_template),
                $this->translatedTemplateValue($this->cta_label_template),
                $this->cta_url_template,
                $latestOverride?->subject_template,
                $latestOverride?->title_template,
                $latestOverride?->body_template,
                $latestOverride?->cta_label_template,
                $latestOverride?->cta_url_template,
                $preview['subject'] ?? null,
                $preview['title'] ?? null,
                $preview['body'] ?? null,
                $preview['cta_label'] ?? null,
                $preview['cta_url'] ?? null,
            ]),
            'flags' => [
                'can_edit_override' => $canEditOverride,
                'can_disable_override' => $canEditOverride && $latestOverride instanceof CommunicationTemplateOverride,
                'can_preview' => true,
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

    protected function translatedTemplateValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return __($value);
    }

    /**
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    protected function availableVariables(array $values): array
    {
        $variables = [];

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            preg_match_all('/(?::|\\{)([a-zA-Z0-9_]+)\\}?/', $value, $matches);

            foreach ($matches[1] ?? [] as $variable) {
                $variables[$variable] = $variable;
            }
        }

        ksort($variables);

        return array_values($variables);
    }
}
