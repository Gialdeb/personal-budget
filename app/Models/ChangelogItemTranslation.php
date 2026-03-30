<?php

namespace App\Models;

use Database\Factories\ChangelogItemTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangelogItemTranslation extends Model
{
    /** @use HasFactory<ChangelogItemTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'locale',
        'title',
        'body',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChangelogItem::class, 'item_id');
    }
}
