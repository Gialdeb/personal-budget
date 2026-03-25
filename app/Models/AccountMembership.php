<?php

namespace App\Models;

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMembership extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'account_id',
        'user_id',
        'household_id',
        'role',
        'status',
        'permissions',
        'granted_by_user_id',
        'source',
        'joined_at',
        'left_at',
        'left_reason',
        'revoked_at',
        'revoked_by_user_id',
        'restored_at',
        'restored_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => AccountMembershipRoleEnum::class,
            'status' => AccountMembershipStatusEnum::class,
            'source' => MembershipSourceEnum::class,
            'permissions' => 'array',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'revoked_at' => 'datetime',
            'restored_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by_user_id');
    }
}
