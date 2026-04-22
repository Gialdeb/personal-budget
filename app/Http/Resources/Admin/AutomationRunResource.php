<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AutomationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $backupArtifact = $this->backupArtifact();

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
            'backup_artifact' => $backupArtifact,
        ];
    }

    protected function backupArtifact(): ?array
    {
        if (! in_array($this->automation_key, ['full_backup', 'user_backup'], true)) {
            return null;
        }

        $result = is_array($this->result) ? $this->result : [];
        $context = is_array($this->context) ? $this->context : [];
        $path = is_string($result['path'] ?? null) ? $result['path'] : null;
        $absolutePath = is_string($result['absolute_path'] ?? null) ? $result['absolute_path'] : null;
        $diskName = is_string($context['backup_disk'] ?? null)
            ? $context['backup_disk']
            : (string) config('automation.backups.disk', 'local');

        if ($path === null) {
            return [
                'disk' => $diskName,
                'path' => null,
                'absolute_path' => $absolutePath,
                'is_available' => false,
            ];
        }

        return [
            'disk' => $diskName,
            'path' => $path,
            'absolute_path' => $absolutePath,
            'is_available' => Storage::disk($diskName)->exists($path),
        ];
    }
}
