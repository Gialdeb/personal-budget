<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderDelivery extends Model
{
    protected $fillable = [
        'user_id',
        'remindable_type',
        'remindable_id',
        'outbound_message_id',
        'reminder_type',
        'due_date',
        'delivery_date',
        'notification_kind',
        'pushed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'delivery_date' => 'date',
        'pushed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
