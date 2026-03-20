<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name',
        'slug',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function userBanks(): HasMany
    {
        return $this->hasMany(UserBank::class);
    }
}
