<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryEmployment extends Model
{
    use HasPublicUuid;

    protected $fillable = ['user_id', 'merchant_id', 'name', 'start_date', 'end_date', 'annual_gross_salary', 'annual_gross_salary_currency'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'annual_gross_salary' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return HasMany<SalarySchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(SalarySchedule::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalaryOccurrenceDetail::class);
    }
}
