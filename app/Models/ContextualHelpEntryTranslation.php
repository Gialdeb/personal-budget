<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextualHelpEntryTranslation extends Model
{
    protected $fillable = [
        'contextual_help_entry_id',
        'locale',
        'title',
        'body',
    ];

    public function contextualHelpEntry(): BelongsTo
    {
        return $this->belongsTo(ContextualHelpEntry::class);
    }
}
