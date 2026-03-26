<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationOutboundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recipient = $this->resource->recipient;
        $context = $this->resource->context;
        $fallbackRecipient = data_get($this->resource->payload_snapshot, 'recipient');

        return [
            'uuid' => $this->resource->uuid,
            'created_at' => $this->resource->created_at?->toJSON(),
            'queued_at' => $this->resource->queued_at?->toJSON(),
            'sent_at' => $this->resource->sent_at?->toJSON(),
            'failed_at' => $this->resource->failed_at?->toJSON(),
            'channel' => $this->resource->channel?->value,
            'channel_label' => __('admin.communication_outbound.channels.'.$this->resource->channel?->value),
            'status' => $this->resource->status?->value,
            'status_label' => __('admin.communication_outbound.statuses.'.$this->resource->status?->value),
            'error_message' => $this->resource->error_message,
            'category' => [
                'uuid' => $this->resource->category?->uuid,
                'key' => $this->resource->category?->key,
                'name' => $this->resource->category?->name,
            ],
            'template' => $this->resource->template ? [
                'uuid' => $this->resource->template->uuid,
                'key' => $this->resource->template->key,
                'name' => $this->resource->template->name,
            ] : null,
            'recipient' => $recipient ? [
                'uuid' => $recipient instanceof User ? $recipient->uuid : null,
                'label' => $recipient instanceof User
                    ? trim(implode(' ', array_filter([$recipient->name, $recipient->surname]))) ?: $recipient->email
                    : class_basename($this->resource->recipient_type),
                'email' => $recipient instanceof User ? $recipient->email : null,
                'type' => class_basename($this->resource->recipient_type),
            ] : ($fallbackRecipient ? [
                'uuid' => null,
                'label' => data_get($fallbackRecipient, 'label') ?? data_get($fallbackRecipient, 'email') ?? __('admin.communication_outbound.empty.noValue'),
                'email' => data_get($fallbackRecipient, 'email'),
                'type' => data_get($fallbackRecipient, 'type', 'Email'),
            ] : null),
            'context' => $context ? [
                'uuid' => data_get($context, 'uuid'),
                'label' => data_get($context, 'name') ?? class_basename($this->resource->context_type),
                'type' => class_basename($this->resource->context_type),
            ] : null,
            'content' => [
                'subject' => $this->resource->subject_resolved,
                'title' => $this->resource->title_resolved,
                'body' => $this->resource->body_resolved,
                'cta_label' => $this->resource->cta_label_resolved,
                'cta_url' => $this->resource->cta_url_resolved,
            ],
        ];
    }
}
