<?php

namespace App\Models;

use App\Enums\CommunicationDeliveryModeEnum;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'key',
        'name',
        'description',
        'audience',
        'delivery_mode',
        'preference_mode',
        'context_type',
        'is_active',
    ];

    protected $casts = [
        'audience' => NotificationAudienceEnum::class,
        'delivery_mode' => CommunicationDeliveryModeEnum::class,
        'preference_mode' => NotificationPreferenceModeEnum::class,
        'is_active' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function channelTemplates(): HasMany
    {
        return $this->hasMany(CommunicationCategoryChannelTemplate::class);
    }
}
