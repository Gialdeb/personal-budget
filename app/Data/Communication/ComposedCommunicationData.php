<?php

namespace App\Data\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateOverride;

class ComposedCommunicationData
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly CommunicationCategory $category,
        public readonly CommunicationChannelEnum $channel,
        public readonly CommunicationTemplate $template,
        public readonly ?CommunicationTemplateOverride $override,
        public readonly array $context,
        public readonly ?string $subject,
        public readonly ?string $title,
        public readonly string $body,
        public readonly ?string $ctaLabel,
        public readonly ?string $ctaUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category' => [
                'uuid' => $this->category->uuid,
                'key' => $this->category->key,
                'name' => $this->category->name,
            ],
            'channel' => $this->channel->value,
            'template' => [
                'uuid' => $this->template->uuid,
                'key' => $this->template->key,
                'name' => $this->template->name,
            ],
            'override' => $this->override ? [
                'uuid' => $this->override->uuid,
            ] : null,
            'context' => $this->context,
            'subject' => $this->subject,
            'title' => $this->title,
            'body' => $this->body,
            'cta_label' => $this->ctaLabel,
            'cta_url' => $this->ctaUrl,
        ];
    }
}
