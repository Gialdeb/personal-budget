<?php

namespace App\Models;

use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackedItemAlias extends Model
{
    use HasAppendOnlyHistory, HasFactory, HasPublicUuid;

    public const UPDATED_AT = null;

    protected $fillable = ['source_tracked_item_id', 'canonical_tracked_item_id', 'created_by_user_id'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'source_tracked_item_id');
    }

    public function canonical(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'canonical_tracked_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
