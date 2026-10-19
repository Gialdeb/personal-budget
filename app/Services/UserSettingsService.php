<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use Closure;
use Illuminate\Support\Facades\DB;

class UserSettingsService
{
    /**
     * Each path is applied to the latest JSON under the same lock. A callback
     * receives the current branch, allowing read/modify/write operations.
     *
     * @param  array<string, mixed|Closure>  $changes
     */
    public function updateBranches(User $user, array $changes): UserSetting
    {
        $settings = DB::transaction(function () use ($user, $changes): UserSetting {
            /** @var User $owner */
            $owner = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            UserSetting::query()->firstOrCreate(['user_id' => $owner->id], [
                'base_currency' => $owner->base_currency_code,
                'settings' => [],
            ]);

            /** @var UserSetting $settings */
            $settings = UserSetting::query()->where('user_id', $owner->id)->lockForUpdate()->firstOrFail();
            $current = $settings->settings ?? [];

            foreach ($changes as $path => $value) {
                data_set($current, $path, $value instanceof Closure ? $value(data_get($current, $path)) : $value);
            }

            $settings->settings = $current;
            $settings->save();

            return $settings;
        }, 3);

        $user->setRelation('settings', $settings);

        return $settings;
    }
}
