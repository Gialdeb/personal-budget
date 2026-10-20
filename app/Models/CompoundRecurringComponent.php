<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompoundRecurringComponent extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name', 'recurring_entry_id'];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    public function displayName(): string
    {
        $parentName = preg_quote($this->compoundRecurringEntry->name, '/');
        $label = trim(preg_replace('/^'.$parentName.'(?:\s*[·•:–—-]\s*|\s+)/iu', '', trim($this->name)) ?? $this->name);

        return $label !== '' ? $label : $this->name;
    }

    public function compoundRecurringEntry(): BelongsTo
    {
        return $this->belongsTo(CompoundRecurringEntry::class);
    }

    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }
}
