<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasPublicUuid;

    protected $fillable = ['support_request_id', 'author_type', 'user_id', 'admin_id', 'body', 'admin_notified_at', 'push_sent_at'];

    protected $casts = ['admin_notified_at' => 'datetime', 'push_sent_at' => 'datetime'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'support_request_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
