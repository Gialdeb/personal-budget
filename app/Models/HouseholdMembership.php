<?php

namespace App\Models;

use App\Enums\HouseholdMembershipStatusEnum;
use App\Enums\HouseholdRoleEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMembership extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'household_id',
        'user_id',
        'role',
        'status',
        'permissions',
        'invited_by_user_id',
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
            'role' => HouseholdRoleEnum::class,
            'status' => HouseholdMembershipStatusEnum::class,
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

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
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
