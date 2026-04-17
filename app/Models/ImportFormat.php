<?php

namespace App\Models;

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportFormat extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'bank_id',
        'code',
        'name',
        'version',
        'type',
        'status',
        'is_generic',
        'notes',
        'settings',
    ];

    protected $casts = [
        'type' => ImportFormatTypeEnum::class,
        'status' => ImportFormatStatusEnum::class,
        'is_generic' => 'boolean',
        'settings' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public static function ensureGenericCsvV1(): self
    {
        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'generic_csv_v1'],
            [
                'name' => 'Template XLSX guidato v1',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::GENERIC_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => true,
                'notes' => 'Formato guidato basato sul template XLSX ufficiale generato dall’app.',
            ]
        );

        if (
            $format->type !== ImportFormatTypeEnum::GENERIC_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || ! $format->is_generic
            || $format->name !== 'Template XLSX guidato v1'
            || $format->notes !== 'Formato guidato basato sul template XLSX ufficiale generato dall’app.'
        ) {
            $format->forceFill([
                'type' => ImportFormatTypeEnum::GENERIC_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => true,
                'name' => 'Template XLSX guidato v1',
                'notes' => 'Formato guidato basato sul template XLSX ufficiale generato dall’app.',
            ])->save();
        }

        return $format->refresh();
    }
}
