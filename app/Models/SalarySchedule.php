<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalarySchedule extends Model
{
    protected $fillable = ['salary_employment_id', 'recurring_entry_id', 'kind', 'label', 'parent_schedule_id', 'valid_from', 'valid_to'];

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_schedule_id');
    }

    public function logicalId(): int
    {
        return $this->parent?->logicalId() ?? $this->id;
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(SalaryEmployment::class, 'salary_employment_id');
    }

    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalaryOccurrenceDetail::class);
    }
}
