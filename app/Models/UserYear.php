<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserYear extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'is_closed',
    ];

    protected $casts = [
        'year' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
