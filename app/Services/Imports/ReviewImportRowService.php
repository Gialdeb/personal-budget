<?php

namespace App\Services\Imports;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use App\Supports\Imports\GenericCsvRowNormalizer;
use App\Supports\Imports\ImportRowStatusResolver;
use Illuminate\Support\Facades\DB;

class ReviewImportRowService
{
    public function __construct(
        protected GenericCsvRowNormalizer $normalizer,
        protected ImportRowStatusResolver $statusResolver,
        protected SyncImportStateService $syncImportStateService,
    ) {}

    public function execute(Import $import, ImportRow $row, array $input): ImportRow
    {
        return DB::transaction(function () use ($import, $row, $input) {
            $managementYear = (int) ($import->meta['management_year'] ?? date('Y'));

            $normalizedResult = $this->normalizer->normalize(
                rawRow: [
                    'date' => $input['date'] ?? null,
                    'type' => $input['type'] ?? null,
                    'amount' => $input['amount'] ?? null,
                    'detail' => $input['detail'] ?? null,
                    'category' => $input['category'] ?? null,
                    'reference' => $input['reference'] ?? null,
                    'merchant' => $input['merchant'] ?? null,
                    'external_reference' => $input['external_reference'] ?? null,
                    'balance' => $input['balance'] ?? null,
                    'destination_account_id' => $input['destination_account_id'] ?? null,
                ],
                routeYear: $managementYear,
                accountId: $import->account_id,
                userId: $import->user_id,
            );

            $normalizedPayload = $normalizedResult['normalized_payload'];
            $fingerprint = $normalizedResult['fingerprint'];

            $destinationAccountId = $input['destination_account_id'] ?? null;
            $destinationAccount = null;

            if ($destinationAccountId) {
                $destinationAccount = Account::query()
                    ->where('id', $destinationAccountId)
                    ->where('user_id', $import->user_id)
                    ->first();
            }

            $normalizedPayload['destination_account_id'] = $destinationAccount?->id ?? ($destinationAccountId ? (int) $destinationAccountId : null);
            $normalizedPayload['destination_account_uuid'] = $destinationAccount?->uuid;

            $alreadyImported = false;
            $duplicateInCurrentImport = false;

            if ($fingerprint) {
                $alreadyImported = ImportRow::query()
                    ->where('fingerprint', $fingerprint)
                    ->whereHas('import', function ($query) use ($import): void {
                        $query->where('account_id', $import->account_id);
                    })
                    ->whereIn('status', ['imported', 'already_imported'])
                    ->where('id', '!=', $row->id)
                    ->exists();

                $duplicateInCurrentImport = ImportRow::query()
                    ->where('import_id', $import->id)
                    ->where('fingerprint', $fingerprint)
                    ->where('id', '!=', $row->id)
                    ->exists();
            }

            $resolved = $this->statusResolver->resolve(
                import: $import,
                normalizedPayload: $normalizedPayload,
                fingerprint: $fingerprint,
                currentRow: $row,
                duplicateInCurrentImport: $duplicateInCurrentImport,
                alreadyImported: $alreadyImported,
            );

            $row->update([
                'normalized_payload' => $normalizedPayload,
                'fingerprint' => $fingerprint,
                'status' => $resolved['status'],
                'errors' => $resolved['errors'],
                'warnings' => $resolved['warnings'],
                'parse_status' => $row->parse_status,
                'parse_error' => empty($resolved['errors']) ? null : implode(' | ', $resolved['errors']),
            ]);

            $this->syncImportStateService->sync($import->fresh());

            return $row->fresh();
        });
    }
}
