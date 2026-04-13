<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequest extends Model
{
    public const CATEGORY_BUG = 'bug';

    public const CATEGORY_FEATURE_REQUEST = 'feature_request';

    public const CATEGORY_GENERAL_SUPPORT = 'general_support';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CLOSED = 'closed';

    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'category',
        'subject',
        'message',
        'locale',
        'source_url',
        'source_route',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_BUG,
            self::CATEGORY_FEATURE_REQUEST,
            self::CATEGORY_GENERAL_SUPPORT,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_CLOSED,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
