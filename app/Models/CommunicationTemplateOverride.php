<?php

namespace App\Models;

use App\Enums\CommunicationTemplateOverrideScopeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateOverride extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'communication_template_id',
        'scope',
        'scope_key',
        'subject_template',
        'title_template',
        'body_template',
        'cta_label_template',
        'cta_url_template',
        'is_active',
    ];

    protected $casts = [
        'scope' => CommunicationTemplateOverrideScopeEnum::class,
        'is_active' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function communicationTemplate(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class);
    }
}
