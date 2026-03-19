<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'active_year',
        'base_currency',
        'settings',
    ];

    protected $guarded = [];

    protected $casts = [
        'active_year' => 'integer',
        'settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDashboardSettings(): array
    {
        return $this->settings['dashboard'] ?? [];
    }

    public function getDashboardSavingsMode(): string
    {
        return $this->settings['dashboard']['savings_mode'] ?? 'net_remaining';
    }

    public function isDashboardBoxVisible(string $box): bool
    {
        return $this->settings['dashboard']['visible_boxes'][$box] ?? true;
    }

    public function isDashboardChartVisible(string $chart): bool
    {
        return $this->settings['dashboard']['visible_charts'][$chart] ?? true;
    }
}
