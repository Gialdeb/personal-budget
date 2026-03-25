<?php

namespace App\Models;

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\InvitationStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountInvitation extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'account_id',
        'household_id',
        'email',
        'role',
        'permissions',
        'invited_by_user_id',
        'token_hash',
        'status',
        'expires_at',
        'accepted_by_user_id',
        'accepted_at',
        'cancelled_by_user_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => AccountMembershipRoleEnum::class,
            'status' => InvitationStatusEnum::class,
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
