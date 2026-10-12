<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRequest extends Model
{
    public const CATEGORY_BUG = 'bug';

    public const CATEGORY_FEATURE_REQUEST = 'feature_request';

    public const CATEGORY_GENERAL_SUPPORT = 'general_support';

    public const STATUS_NEW = 'waiting_admin';

    public const STATUS_ANSWERED = 'waiting_user';

    public const STATUS_IN_PROGRESS = 'waiting_admin';

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
        'inactivity_warned_at', 'auto_close_at', 'meta', 'app_version', 'closed_at', 'last_admin_reply_at', 'last_user_message_at',
    ];

    protected $casts = [
        'inactivity_warned_at' => 'datetime', 'auto_close_at' => 'datetime', 'meta' => 'array', 'closed_at' => 'datetime', 'last_admin_reply_at' => 'datetime', 'last_user_message_at' => 'datetime',
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
            self::STATUS_ANSWERED,
            self::STATUS_CLOSED,
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
