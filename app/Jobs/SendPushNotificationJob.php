<?php

namespace App\Jobs;

use App\Models\PushBroadcast;
use App\Services\Audit\AuditLogService;
use App\Services\Push\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $pushBroadcastId,
    ) {
        $this->onQueue((string) config('push-notifications.queue', 'default'));
    }

    public function handle(
        PushNotificationService $pushNotificationService,
        AuditLogService $auditLogService,
    ): void {
        $broadcast = PushBroadcast::query()->with('creator')->find($this->pushBroadcastId);

        if (! $broadcast instanceof PushBroadcast) {
            return;
        }

        $broadcast->forceFill([
            'status' => 'sending',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $summary = $pushNotificationService->sendBroadcast($broadcast);

            $broadcast->forceFill([
                'status' => $summary['failed_count'] > 0 ? 'completed_with_failures' : 'completed',
                'eligible_users_count' => $summary['eligible_users_count'],
                'target_tokens_count' => $summary['target_tokens_count'],
                'sent_count' => $summary['sent_count'],
                'failed_count' => $summary['failed_count'],
                'invalidated_count' => $summary['invalidated_count'],
                'finished_at' => now(),
            ])->save();

            if ($broadcast->creator !== null) {
                $auditLogService->pushBroadcastCompleted($broadcast->creator, $broadcast, $summary);
            }
        } catch (Throwable $exception) {
            $broadcast->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            if ($broadcast->creator !== null) {
                $auditLogService->pushBroadcastFailed($broadcast->creator, $broadcast, $exception->getMessage());
            }

            Log::error('Push broadcast delivery failed.', [
                'broadcast_uuid' => $broadcast->uuid,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
