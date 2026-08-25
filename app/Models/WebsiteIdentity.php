<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\WebsiteIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WebsiteIdentity extends Model
{
    /** @use HasFactory<WebsiteIdentityFactory> */
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'domain',
        'canonical_url',
        'logo_path',
        'logo_mime_type',
        'logo_source_url',
        'status',
        'fetched_at',
        'retry_after',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'immutable_datetime',
            'retry_after' => 'immutable_datetime',
        ];
    }

    public function trackedItems(): HasMany
    {
        return $this->hasMany(TrackedItem::class);
    }

    public function hasStoredLogo(): bool
    {
        return $this->status === 'ready'
            && is_string($this->logo_path)
            && $this->logo_path !== ''
            && Storage::disk('public')->exists($this->logo_path);
    }

    public function logoUrl(): ?string
    {
        return $this->status === 'ready' && filled($this->logo_path)
            ? route('tracked-item-logos.show', $this->uuid)
            : null;
    }
}
