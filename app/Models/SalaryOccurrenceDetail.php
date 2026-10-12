<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryOccurrenceDetail extends Model
{
    protected $fillable = ['recurring_entry_occurrence_id', 'salary_employment_id', 'salary_schedule_id', 'pay_period_start', 'pay_period_end', 'base_expected_amount', 'currency', 'is_overridden'];

    protected $casts = ['pay_period_start' => 'date', 'pay_period_end' => 'date', 'base_expected_amount' => 'decimal:2', 'is_overridden' => 'boolean'];

    public function expectedTotal(): float
    {
        return (round((float) $this->base_expected_amount * 100) + $this->components->sum(fn ($component): int => (int) round((float) $component->amount * 100))) / 100;
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringEntryOccurrence::class, 'recurring_entry_occurrence_id');
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(SalaryEmployment::class, 'salary_employment_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SalarySchedule::class, 'salary_schedule_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryOccurrenceComponent::class);
    }
}
