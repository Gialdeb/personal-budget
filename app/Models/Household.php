<?php

namespace App\Models;

use App\Enums\HouseholdMembershipStatusEnum;
use App\Enums\HouseholdStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'owner_user_id',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => HouseholdStatusEnum::class,
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class)
            ->where('status', HouseholdMembershipStatusEnum::ACTIVE->value);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_memberships')
            ->withPivot([
                'uuid',
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
            ])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
