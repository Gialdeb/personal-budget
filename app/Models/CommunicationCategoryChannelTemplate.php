<?php

namespace App\Models;

use App\Enums\CommunicationChannelEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationCategoryChannelTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'communication_category_id',
        'communication_template_id',
        'channel',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'channel' => CommunicationChannelEnum::class,
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CommunicationCategory::class, 'communication_category_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }
}
