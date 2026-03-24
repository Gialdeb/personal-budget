<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'automation_key' => $this->automation_key,
            'pipeline' => $this->pipeline,
            'job_class' => $this->job_class,
            'status' => $this->status?->value,
            'trigger_type' => $this->trigger_type?->value,
            'started_at' => $this->started_at?->toDateTimeString(),
            'finished_at' => $this->finished_at?->toDateTimeString(),
            'duration_ms' => $this->duration_ms,
            'processed_count' => $this->processed_count,
            'success_count' => $this->success_count,
            'warning_count' => $this->warning_count,
            'error_count' => $this->error_count,
            'batch_id' => $this->batch_id,
            'attempt' => $this->attempt,
            'host' => $this->host,
            'context' => $this->context ?? [],
            'result' => $this->result ?? [],
            'error_message' => $this->error_message,
            'exception_class' => $this->exception_class,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'is_retryable' => in_array($this->status?->value, ['failed', 'timed_out', 'warning'], true),
        ];
    }
}
