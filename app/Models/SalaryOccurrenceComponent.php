<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryOccurrenceComponent extends Model
{
    protected $fillable = ['salary_occurrence_detail_id', 'label', 'amount', 'type', 'sort_order'];

    protected $casts = ['amount' => 'decimal:2', 'sort_order' => 'integer'];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(SalaryOccurrenceDetail::class, 'salary_occurrence_detail_id');
    }
}
