<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\TransactionTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionTemplate extends Model
{
    /** @use HasFactory<TransactionTemplateFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = ['name', 'icon', 'color', 'direction', 'account_id', 'category_id', 'tracked_item_id', 'description', 'notes', 'default_amount', 'shared_enabled', 'shared_counterparty_id', 'shared_payer', 'shared_split_mode', 'my_share_basis_points', 'reference_snapshots', 'is_active', 'sort_order'];

    protected $attributes = ['icon' => 'Zap', 'color' => 'primary', 'shared_enabled' => false, 'shared_payer' => 'self', 'shared_split_mode' => 'equal', 'my_share_basis_points' => 5000, 'is_active' => true, 'sort_order' => 0, 'usage_count' => 0];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2', 'shared_enabled' => 'boolean', 'is_active' => 'boolean', 'reference_snapshots' => 'array', 'my_share_basis_points' => 'integer', 'sort_order' => 'integer', 'usage_count' => 'integer', 'last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function trackedItem(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class);
    }

    public function sharedCounterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'shared_counterparty_id');
    }

    /** @return array<string, mixed> */
    public function prefill(): array
    {
        $this->loadMissing(['account', 'category', 'trackedItem', 'sharedCounterparty']);
        $issues = [];
        $references = [];
        foreach (['account' => $this->account, 'category' => $this->category, 'tracked_item' => $this->trackedItem, 'shared_counterparty' => $this->sharedCounterparty] as $key => $record) {
            $valid = $record && $record->user_id === $this->user_id && $record->is_active;
            if ($key === 'shared_counterparty') {
                $valid = $valid && $record->is_shared_expense_counterpart;
            }
            if ($key === 'category') {
                $valid = $valid && $record->is_selectable && in_array($record->direction_type?->value, [$this->direction, 'mixed'], true) && (! $record->account_id || $record->account_id === $this->account_id);
            }
            $references[$key.'_uuid'] = $valid ? $record->uuid : null;
            if (! $valid && ! empty($this->reference_snapshots[$key])) {
                $issues[$key] = $this->reference_snapshots[$key].__('ui.no_longer_available_replace_this_value_before_saving_c93b8a1f');
            }
        }

        return [...$this->only(['uuid', 'name', 'icon', 'color', 'direction', 'description', 'notes', 'default_amount', 'shared_enabled', 'shared_payer', 'shared_split_mode', 'my_share_basis_points', 'is_active', 'sort_order', 'usage_count', 'last_used_at']), ...$references, 'issues' => $issues];
    }
}
