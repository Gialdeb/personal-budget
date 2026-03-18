<?php

namespace App\Models;

use App\Enums\AccountBalanceNatureEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'balance_nature',
    ];

    protected $casts = [
        'balance_nature' => AccountBalanceNatureEnum::class,
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
