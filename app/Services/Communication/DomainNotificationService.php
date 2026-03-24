<?php

namespace App\Services\Communication;

use App\Models\Import;
use App\Models\User;
use Illuminate\Support\Collection;

class DomainNotificationService
{
    public function __construct(
        protected CommunicationService $communicationService,
    ) {}

    public function sendAutomationFailed(array $payload = []): Collection
    {
        return $this->communicationService->send(
            topicKey: 'automation_failed',
            payload: $payload,
        );
    }

    public function sendImportCompleted(Import $import): Collection
    {
        return $this->communicationService->send(
            topicKey: 'import_completed',
            payload: [
                'import_uuid' => $import->uuid,
                'original_filename' => $import->original_filename,
                'rows_count' => $import->rows_count,
                'imported_rows_count' => $import->imported_rows_count,
            ],
            target: $import->user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendMonthlyReportReady(User $user, array $payload = []): Collection
    {
        return $this->communicationService->send(
            topicKey: 'monthly_report_ready',
            payload: $payload,
            target: $user,
        );
    }
}
