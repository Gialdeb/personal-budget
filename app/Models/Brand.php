<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = ['name', 'aliases', 'domain', 'category', 'logo_path', 'logo_mime_type', 'is_published'];

    protected $attributes = ['is_published' => false];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'is_published' => 'boolean'];
    }

    public static function normalize(string $value): string
    {
        return Str::lower(Str::ascii(Str::squish($value)));
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $brand->name = Str::squish($brand->name);
            $brand->domain = Str::lower($brand->domain);
            $brand->normalized_name = self::normalize($brand->name);
            $brand->identity_key = hash('sha256', $brand->normalized_name.'|'.$brand->domain);
            $brand->search_text = self::normalize(implode(' ', [$brand->name, $brand->domain, ...($brand->aliases ?? [])]));
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeSearch(Builder $query, ?string $search): void
    {
        if (filled($search)) {
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], self::normalize($search));
            $query->where('search_text', 'like', '%'.$term.'%');
        }
    }
}
