<?php

namespace App\Models;

use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTopic extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'key',
        'name',
        'description',
        'audience',
        'supports_email',
        'supports_in_app',
        'supports_sms',
        'default_email_enabled',
        'default_in_app_enabled',
        'default_sms_enabled',
        'is_user_configurable',
        'is_active',
        'preference_mode',
    ];

    protected $casts = [
        'audience' => NotificationAudienceEnum::class,
        'supports_email' => 'boolean',
        'supports_in_app' => 'boolean',
        'supports_sms' => 'boolean',
        'default_email_enabled' => 'boolean',
        'default_in_app_enabled' => 'boolean',
        'default_sms_enabled' => 'boolean',
        'is_user_configurable' => 'boolean',
        'is_active' => 'boolean',
        'preference_mode' => NotificationPreferenceModeEnum::class,
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }
}
