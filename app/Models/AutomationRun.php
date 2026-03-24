<?php

namespace App\Models;

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'automation_key',
        'pipeline',
        'job_class',
        'status',
        'trigger_type',
        'started_at',
        'finished_at',
        'duration_ms',
        'processed_count',
        'success_count',
        'warning_count',
        'error_count',
        'batch_id',
        'attempt',
        'host',
        'context',
        'result',
        'error_message',
        'exception_class',
    ];

    protected $casts = [
        'status' => AutomationRunStatusEnum::class,
        'trigger_type' => AutomationTriggerTypeEnum::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'processed_count' => 'integer',
        'success_count' => 'integer',
        'warning_count' => 'integer',
        'error_count' => 'integer',
        'attempt' => 'integer',
        'context' => 'array',
        'result' => 'array',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
