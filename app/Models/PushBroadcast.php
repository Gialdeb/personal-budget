<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushBroadcast extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'created_by',
        'status',
        'title',
        'body',
        'url',
        'eligible_users_count',
        'target_tokens_count',
        'sent_count',
        'failed_count',
        'invalidated_count',
        'payload_snapshot',
        'error_message',
        'queued_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload_snapshot' => 'array',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
