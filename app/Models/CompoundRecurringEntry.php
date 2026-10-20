<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CompoundRecurringEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompoundRecurringEntry extends Model
{
    /** @use HasFactory<CompoundRecurringEntryFactory> */
    use HasFactory, HasPublicUuid;

    protected $fillable = ['name', 'user_id', 'account_id', 'category_id', 'currency', 'direction'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(CompoundRecurringComponent::class)->orderBy('id');
    }

    public function derivedStatus(): string
    {
        $statuses = $this->components->whereNull('archived_at')->map(fn (CompoundRecurringComponent $component): string => $component->recurringEntry->status->value)->unique();

        return $statuses->isEmpty() ? 'cancelled' : ($statuses->count() === 1 ? $statuses->first() : 'mixed');
    }
}
