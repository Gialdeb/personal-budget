<?php

namespace App\Services\Imports;

use App\Enums\ImportRowStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\TrackedItem;
use App\Models\Transaction;
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
            $currentPayload = is_array($row->normalized_payload) ? $row->normalized_payload : [];
            $sourceAccountId = isset($input['account_id'])
                ? (int) $input['account_id']
                : ($currentPayload['account_id'] ?? $import->account_id);
            $selectedCategory = $this->resolveCategory($import->user_id, $input);
            $selectedTrackedItem = $this->resolveTrackedItem($import->user_id, $input);

            $normalizedResult = $this->normalizer->normalize(
                rawRow: [
                    'account_id' => $sourceAccountId,
                    'date' => $input['date'] ?? null,
                    'type' => $input['type'] ?? null,
                    'amount' => $input['amount'] ?? null,
                    'detail' => $input['detail'] ?? null,
                    'category' => $selectedCategory?->name ?? ($input['category'] ?? null),
                    'reference' => array_key_exists('reference', $input)
                        ? $input['reference']
                        : ($currentPayload['reference'] ?? null),
                    'merchant' => $input['merchant'] ?? null,
                    'external_reference' => $input['external_reference'] ?? null,
                    'destination_account_id' => $input['destination_account_id'] ?? null,
                ],
                routeYear: $managementYear,
                accountId: $sourceAccountId ? (int) $sourceAccountId : null,
                userId: $import->user_id,
            );

            $normalizedPayload = $normalizedResult['normalized_payload'];
            $fingerprint = $normalizedResult['fingerprint'];
            $sourceAccount = null;

            if ($sourceAccountId) {
                $sourceAccount = Account::query()
                    ->where('id', $sourceAccountId)
                    ->where('user_id', $import->user_id)
                    ->where('is_active', true)
                    ->first();
            }

            $normalizedPayload['account_id'] = $sourceAccount?->id;
            $normalizedPayload['account_uuid'] = $sourceAccount?->uuid;
            $normalizedPayload['category_uuid'] = $selectedCategory?->uuid;
            $normalizedPayload['tracked_item_id'] = $selectedTrackedItem?->id;
            $normalizedPayload['tracked_item_uuid'] = $selectedTrackedItem?->uuid;

            $destinationAccountId = $normalizedPayload['type'] === 'transfer'
                ? ($input['destination_account_id'] ?? null)
                : null;
            $destinationAccount = null;

            if ($destinationAccountId) {
                $destinationAccount = Account::query()
                    ->where('id', $destinationAccountId)
                    ->where('user_id', $import->user_id)
                    ->first();
            }

            $normalizedPayload['destination_account_id'] = $destinationAccount?->id ?? ($destinationAccountId ? (int) $destinationAccountId : null);
            $normalizedPayload['destination_account_uuid'] = $destinationAccount?->uuid;

            $duplicateInCurrentImport = false;
            $duplicateSignal = null;

            if ($fingerprint) {
                $duplicateInCurrentImport = ImportRow::query()
                    ->where('import_id', $import->id)
                    ->where('fingerprint', $fingerprint)
                    ->where('id', '!=', $row->id)
                    ->exists();

                $duplicateSignal = $this->duplicateSignal(
                    fingerprint: $fingerprint,
                    accountId: $normalizedPayload['account_id'] ?? null,
                    currentRow: $row,
                );
            }

            $resolved = $this->statusResolver->resolve(
                import: $import,
                normalizedPayload: $normalizedPayload,
                fingerprint: $fingerprint,
                currentRow: $row,
                duplicateInCurrentImport: $duplicateInCurrentImport,
                alreadyImported: false,
            );

            if (
                $duplicateSignal !== null
                && ! in_array($resolved['status'], [
                    ImportRowStatusEnum::INVALID->value,
                    ImportRowStatusEnum::BLOCKED_YEAR->value,
                ], true)
            ) {
                $resolved['status'] = ImportRowStatusEnum::DUPLICATE_CANDIDATE->value;
                $resolved['warnings'][] = $this->duplicateWarningMessage($duplicateSignal);
                $resolved['warnings'] = array_values(array_unique($resolved['warnings']));
            }

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

    protected function duplicateSignal(string $fingerprint, ?int $accountId, ImportRow $currentRow): ?string
    {
        if ($accountId === null) {
            return null;
        }

        $matchingImportRows = ImportRow::query()
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $currentRow->id)
            ->where(function ($query) use ($accountId): void {
                $query
                    ->where('normalized_payload->account_id', $accountId)
                    ->orWhereHas('import', function ($importQuery) use ($accountId): void {
                        $importQuery->where('account_id', $accountId);
                    });
            })
            ->whereIn('status', ['imported', 'already_imported']);

        if (! $matchingImportRows->exists()) {
            return null;
        }

        $activeLedgerMatchExists = Transaction::query()
            ->where('account_id', $accountId)
            ->where('external_hash', $fingerprint)
            ->exists()
            || (clone $matchingImportRows)
                ->whereHas('transaction')
                ->exists();

        return $activeLedgerMatchExists
            ? 'active_transaction'
            : 'historical_import';
    }

    protected function duplicateWarningMessage(string $signal): string
    {
        return match ($signal) {
            'active_transaction' => __('imports.validation.duplicate_existing_transaction'),
            'historical_import' => __('imports.validation.duplicate_historical_import'),
            default => __('imports.validation.already_imported'),
        };
    }

    protected function resolveCategory(int $userId, array $input): ?Category
    {
        $categoryUuid = $input['category_uuid'] ?? null;

        if (is_string($categoryUuid) && $categoryUuid !== '') {
            return Category::query()
                ->ownedBy($userId)
                ->where('is_active', true)
                ->where('is_selectable', true)
                ->where('uuid', $categoryUuid)
                ->first();
        }

        return null;
    }

    protected function resolveTrackedItem(int $userId, array $input): ?TrackedItem
    {
        $trackedItemUuid = $input['tracked_item_uuid'] ?? null;

        if (! is_string($trackedItemUuid) || $trackedItemUuid === '') {
            return null;
        }

        return TrackedItem::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->where('uuid', $trackedItemUuid)
            ->first();
    }
}
