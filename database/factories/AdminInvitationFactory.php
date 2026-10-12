<?php

namespace Database\Factories;

use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<AdminInvitation> */
class AdminInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'target_user_id' => User::factory(),
            'invited_by_user_id' => User::factory(),
            'otp_hash' => Hash::make('123456'),
            'email_hash' => fn (array $attributes): string => hash('sha256', User::findOrFail($attributes['target_user_id'])->email),
            'active_target_user_id' => fn (array $attributes): int => $attributes['target_user_id'],
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
