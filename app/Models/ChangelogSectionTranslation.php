<?php

namespace App\Models;

use Database\Factories\ChangelogSectionTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangelogSectionTranslation extends Model
{
    /** @use HasFactory<ChangelogSectionTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'section_id',
        'locale',
        'label',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ChangelogSection::class, 'section_id');
    }
}
