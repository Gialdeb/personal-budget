<?php

namespace App\Services\Sharing;

use App\Enums\HouseholdMembershipStatusEnum;
use App\Enums\HouseholdRoleEnum;
use App\Enums\HouseholdStatusEnum;
use App\Models\Household;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Support\Str;

class PersonalHouseholdService
{
    public function getOrCreateForUser(User $user): Household
    {
        $household = Household::query()->firstOrCreate(
            [
                'owner_user_id' => $user->id,
                'slug' => 'user-'.$user->id.'-personal',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => trim(($user->name ?: 'User').' personal'),
                'status' => HouseholdStatusEnum::ACTIVE,
                'settings' => null,
            ]
        );

        HouseholdMembership::query()->firstOrCreate(
            [
                'household_id' => $household->id,
                'user_id' => $user->id,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'role' => HouseholdRoleEnum::OWNER,
                'status' => HouseholdMembershipStatusEnum::ACTIVE,
                'permissions' => null,
                'invited_by_user_id' => null,
                'joined_at' => now(),
            ]
        );

        return $household;
    }
}
