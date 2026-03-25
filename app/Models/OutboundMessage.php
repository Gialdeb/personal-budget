<?php

namespace App\Models;

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OutboundMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'communication_category_id',
        'communication_template_id',
        'channel',
        'status',
        'recipient_type',
        'recipient_id',
        'context_type',
        'context_id',
        'subject_resolved',
        'title_resolved',
        'body_resolved',
        'cta_label_resolved',
        'cta_url_resolved',
        'payload_snapshot',
        'queued_at',
        'sent_at',
        'failed_at',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'channel' => CommunicationChannelEnum::class,
        'status' => OutboundMessageStatusEnum::class,
        'payload_snapshot' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CommunicationCategory::class, 'communication_category_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'recipient_type', 'recipient_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'context_type', 'context_id');
    }
}
