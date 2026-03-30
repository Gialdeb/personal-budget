<?php

namespace App\Models;

use Database\Factories\ChangelogReleaseTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangelogReleaseTranslation extends Model
{
    /** @use HasFactory<ChangelogReleaseTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'release_id',
        'locale',
        'title',
        'summary',
        'excerpt',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(ChangelogRelease::class, 'release_id');
    }
}
