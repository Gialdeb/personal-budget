<?php

namespace App\Models;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'key',
        'notification_topic_id',
        'channel',
        'template_mode',
        'name',
        'description',
        'subject_template',
        'title_template',
        'body_template',
        'cta_label_template',
        'cta_url_template',
        'is_system_locked',
        'is_active',
    ];

    protected $casts = [
        'channel' => CommunicationChannelEnum::class,
        'template_mode' => CommunicationTemplateModeEnum::class,
        'is_system_locked' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function notificationTopic(): BelongsTo
    {
        return $this->belongsTo(NotificationTopic::class, 'notification_topic_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(CommunicationTemplateOverride::class);
    }

    public function categoryTemplateMappings(): HasMany
    {
        return $this->hasMany(CommunicationCategoryChannelTemplate::class);
    }
}
