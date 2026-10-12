<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminInvitation extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = ['target_user_id', 'invited_by_user_id', 'active_target_user_id', 'email_hash', 'otp_hash', 'expires_at', 'accepted_at', 'cancelled_at', 'attempts'];

    protected $hidden = ['otp_hash', 'email_hash'];

    protected $attributes = ['attempts' => 0];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'cancelled_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->active_target_user_id !== null
            && $this->accepted_at === null
            && $this->cancelled_at === null
            && $this->otp_hash !== null
            && $this->expires_at->isFuture()
            && $this->attempts < 5;
    }
}
