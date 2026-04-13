<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSectionTranslation extends Model
{
    protected $fillable = [
        'section_id',
        'locale',
        'title',
        'description',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSection::class, 'section_id');
    }
}
