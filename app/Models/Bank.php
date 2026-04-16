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
        'display_name',
        'slug',
        'country_code',
        'riad_code',
        'lei',
        'address',
        'postal_code',
        'city',
        'category',
        'head_country_code',
        'head_name',
        'head_riad_code',
        'head_lei',
        'report_label',
        'logo_path',
        'logo_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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

    public function presentableName(): string
    {
        return $this->display_name ?: $this->name;
    }
}
