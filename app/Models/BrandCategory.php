<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandCategory extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name'];

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'category', 'name');
    }
}
