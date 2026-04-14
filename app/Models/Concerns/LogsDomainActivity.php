<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;

trait LogsDomainActivity
{
    /**
     * @var array<int, string>
     */
    protected static array $recordEvents = ['created', 'updated', 'deleted'];

    /**
     * @param  array<int, string>  $attributes
     */
    protected function domainActivityLogOptions(string $logName, string $descriptionPrefix, array $attributes): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($logName)
            ->logOnly($attributes)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName): string => "{$descriptionPrefix}.{$eventName}");
    }
}
