<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Operational diagnostics, not economic history. */
class CounterpartySyncIssue extends Model
{
    protected $fillable = ['user_id', 'counterparty_id', 'currency_code', 'source_type', 'source_identity', 'message', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
