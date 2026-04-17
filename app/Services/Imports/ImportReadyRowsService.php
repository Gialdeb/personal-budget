<?php

namespace App\Services\Imports;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Merchant;
use App\Models\TrackedItem;
use App\Services\Transactions\TransactionMutationService;
use App\Supports\CategoryHierarchy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportReadyRowsService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService,
        protected SyncImportStateService $syncImportStateService
    ) {}

    public function execute(Import $import): Import
    {
        $import->loadMissing(['user', 'account', 'rows']);

        $readyRows = $import->rows
            ->where('status', ImportRowStatusEnum::READY)
            ->sortBy('row_index')
            ->values();

        if ($readyRows->isEmpty()) {
            throw ValidationException::withMessages([
                'import' => 'Non ci sono righe pronte da importare.',
            ]);
        }

        DB::transaction(function () use ($import, $readyRows): void {
            /** @var ImportRow $row */
            foreach ($readyRows as $row) {
                $normalizedPayload = $row->normalized_payload ?? [];
                $normalizedType = (string) ($normalizedPayload['type'] ?? 'expense');
                $sourceAccount = $this->resolveSourceAccount($import, $normalizedPayload);

                if (! $sourceAccount instanceof Account) {
                    $warnings = $row->warnings ?? [];
                    $warnings[] = __('imports.validation.account_missing_review');

                    $row->forceFill([
                        'status' => ImportRowStatusEnum::NEEDS_REVIEW,
                        'warnings' => array_values(array_unique($warnings)),
                    ])->save();

                    continue;
                }

                if ($normalizedType === 'transfer') {
                    $this->importTransferRow($import, $row, $normalizedPayload, $sourceAccount);

                    continue;
                }

                $categoryLabel = (string) ($normalizedPayload['category'] ?? '');
                $category = $this->resolveCategory($import->user_id, $categoryLabel);

                if (! $category instanceof Category) {
                    $warnings = $row->warnings ?? [];
                    $warnings[] = "La categoria {$categoryLabel} non è disponibile nel gestionale e la riga richiede revisione.";

                    $row->forceFill([
                        'status' => ImportRowStatusEnum::NEEDS_REVIEW,
                        'warnings' => array_values(array_unique($warnings)),
                    ])->save();

                    continue;
                }

                $transaction = $this->transactionMutationService->store($import->user, [
                    'transaction_day' => (int) Str::of((string) ($normalizedPayload['date'] ?? ''))->afterLast('-')->value(),
                    'transaction_date' => $normalizedPayload['date'],
                    'type_key' => $this->typeKeyFromNormalizedType($normalizedType),
                    'account_id' => $sourceAccount->id,
                    'category_id' => $category->id,
                    'tracked_item_id' => $this->resolveTrackedItemId($import->user_id, $normalizedPayload),
                    'amount' => (float) ($normalizedPayload['amount'] ?? 0),
                    'description' => $normalizedPayload['detail'] ?? null,
                    'notes' => null,
                ]);

                $merchant = $this->resolveMerchant($import->user_id, (string) ($normalizedPayload['merchant'] ?? ''));

                $transaction->forceFill([
                    'import_id' => $import->id,
                    'import_row_id' => $row->id,
                    'source_type' => TransactionSourceTypeEnum::IMPORT,
                    'merchant_id' => $merchant?->id,
                    'bank_description_raw' => $row->raw_description,
                    'bank_description_clean' => $normalizedPayload['detail'] ?? $row->raw_description,
                    'counterparty_name' => $normalizedPayload['merchant'] ?? null,
                    'reference_code' => $normalizedPayload['reference'] ?? null,
                    'external_hash' => $row->fingerprint,
                    'balance_after' => is_numeric($normalizedPayload['balance'] ?? null)
                        ? round((float) $normalizedPayload['balance'], 2)
                        : $transaction->balance_after,
                ])->save();

                $row->forceFill([
                    'status' => ImportRowStatusEnum::IMPORTED,
                    'transaction_id' => $transaction->id,
                    'imported_at' => now(),
                ])->save();
            }
        });

        return $this->syncImportStateService->sync($import->fresh(['rows']));
    }

    protected function importTransferRow(Import $import, ImportRow $row, array $normalizedPayload, Account $sourceAccount): void
    {
        $destinationAccountId = $normalizedPayload['destination_account_id'] ?? null;
        $categoryLabel = (string) ($normalizedPayload['category'] ?? '');
        $category = $this->resolveCategory($import->user_id, $categoryLabel);

        if (! $destinationAccountId) {
            $warnings = $row->warnings ?? [];
            $warnings[] = 'Il giroconto richiede un conto destinazione prima dell\'import nelle transazioni.';

            $row->forceFill([
                'status' => ImportRowStatusEnum::NEEDS_REVIEW,
                'warnings' => array_values(array_unique($warnings)),
            ])->save();

            return;
        }

        $destinationAccount = Account::query()
            ->where('id', $destinationAccountId)
            ->where('user_id', $import->user_id)
            ->where('is_active', true)
            ->first();

        if (! $destinationAccount instanceof Account) {
            $warnings = $row->warnings ?? [];
            $warnings[] = 'Il conto destinazione del giroconto non è valido e la riga richiede revisione.';

            $row->forceFill([
                'status' => ImportRowStatusEnum::NEEDS_REVIEW,
                'warnings' => array_values(array_unique($warnings)),
            ])->save();

            return;
        }

        if ((int) $destinationAccount->id === (int) $sourceAccount->id) {
            $warnings = $row->warnings ?? [];
            $warnings[] = 'Il conto destinazione del giroconto deve essere diverso dal conto di origine.';

            $row->forceFill([
                'status' => ImportRowStatusEnum::NEEDS_REVIEW,
                'warnings' => array_values(array_unique($warnings)),
            ])->save();

            return;
        }

        if (! $category instanceof Category) {
            $warnings = $row->warnings ?? [];
            $warnings[] = "La categoria {$categoryLabel} non è disponibile nel gestionale e la riga richiede revisione.";

            $row->forceFill([
                'status' => ImportRowStatusEnum::NEEDS_REVIEW,
                'warnings' => array_values(array_unique($warnings)),
            ])->save();

            return;
        }

        $amount = (float) ($normalizedPayload['amount'] ?? 0);
        $date = $normalizedPayload['date'] ?? null;
        $day = (int) Str::of((string) $date)->afterLast('-')->value();
        $detail = $normalizedPayload['detail'] ?? null;
        $reference = $normalizedPayload['reference'] ?? null;
        $externalHash = $row->fingerprint;

        $outgoingTransaction = $this->transactionMutationService->store($import->user, [
            'transaction_day' => $day,
            'transaction_date' => $date,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $sourceAccount->id,
            'category_id' => $category->id,
            'tracked_item_id' => $this->resolveTrackedItemId($import->user_id, $normalizedPayload),
            'amount' => $amount,
            'description' => $detail,
            'notes' => null,
        ]);

        $incomingTransaction = $this->transactionMutationService->store($import->user, [
            'transaction_day' => $day,
            'transaction_date' => $date,
            'type_key' => CategoryGroupTypeEnum::INCOME->value,
            'account_id' => $destinationAccount->id,
            'category_id' => $category->id,
            'tracked_item_id' => $this->resolveTrackedItemId($import->user_id, $normalizedPayload),
            'amount' => $amount,
            'description' => $detail,
            'notes' => null,
        ]);

        $outgoingTransaction->forceFill([
            'import_id' => $import->id,
            'import_row_id' => $row->id,
            'source_type' => TransactionSourceTypeEnum::IMPORT,
            'merchant_id' => null,
            'bank_description_raw' => $row->raw_description,
            'bank_description_clean' => $detail ?? $row->raw_description,
            'counterparty_name' => $destinationAccount->name,
            'reference_code' => $reference,
            'external_hash' => $externalHash,
            'related_transaction_id' => $incomingTransaction->id,
            'balance_after' => is_numeric($normalizedPayload['balance'] ?? null)
                ? round((float) $normalizedPayload['balance'], 2)
                : $outgoingTransaction->balance_after,
        ])->save();

        $incomingTransaction->forceFill([
            'import_id' => $import->id,
            'import_row_id' => $row->id,
            'source_type' => TransactionSourceTypeEnum::IMPORT,
            'merchant_id' => null,
            'bank_description_raw' => $row->raw_description,
            'bank_description_clean' => $detail ?? $row->raw_description,
            'counterparty_name' => $sourceAccount->name,
            'reference_code' => $reference,
            'external_hash' => $externalHash ? $externalHash.':pair' : null,
            'related_transaction_id' => $outgoingTransaction->id,
        ])->save();

        $row->forceFill([
            'status' => ImportRowStatusEnum::IMPORTED,
            'transaction_id' => $outgoingTransaction->id,
            'imported_at' => now(),
        ])->save();
    }

    protected function resolveSourceAccount(Import $import, array $normalizedPayload): ?Account
    {
        $sourceAccountId = $normalizedPayload['account_id'] ?? $import->account_id;

        if (! $sourceAccountId) {
            return null;
        }

        return Account::query()
            ->where('id', $sourceAccountId)
            ->where('user_id', $import->user_id)
            ->where('is_active', true)
            ->first();
    }

    protected function resolveCategory(int $userId, string $label): ?Category
    {
        if (blank($label)) {
            return null;
        }

        $categories = Category::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
            ]);

        $normalizedLabel = mb_strtolower(trim($label));
        $slug = Str::slug($label);
        $matchedCategoryId = collect(CategoryHierarchy::buildFlat($categories))
            ->first(fn (array $category): bool => (bool) $category['is_selectable']
                && (mb_strtolower((string) $category['name']) === $normalizedLabel
                || mb_strtolower((string) $category['full_path']) === $normalizedLabel
                || (string) $category['slug'] === $slug))['id'] ?? null;

        if ($matchedCategoryId === null) {
            return null;
        }

        return $categories->firstWhere('id', $matchedCategoryId);
    }

    protected function resolveMerchant(int $userId, string $label): ?Merchant
    {
        if (blank($label)) {
            return null;
        }

        $normalizedLabel = mb_strtolower($label);

        return Merchant::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($normalizedLabel): void {
                $query
                    ->whereRaw('LOWER(name) = ?', [$normalizedLabel])
                    ->orWhere('normalized_name', $normalizedLabel);
            })
            ->first();
    }

    protected function resolveTrackedItemId(int $userId, array $normalizedPayload): ?int
    {
        $trackedItemId = $normalizedPayload['tracked_item_id'] ?? null;

        if (is_numeric($trackedItemId)) {
            return TrackedItem::query()
                ->ownedBy($userId)
                ->where('is_active', true)
                ->whereKey((int) $trackedItemId)
                ->value('id');
        }

        $trackedItemUuid = $normalizedPayload['tracked_item_uuid'] ?? null;

        if (! is_string($trackedItemUuid) || $trackedItemUuid === '') {
            return null;
        }

        return TrackedItem::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->where('uuid', $trackedItemUuid)
            ->value('id');
    }

    protected function typeKeyFromNormalizedType(string $type): string
    {
        return match ($type) {
            'income' => CategoryGroupTypeEnum::INCOME->value,
            'bill' => CategoryGroupTypeEnum::BILL->value,
            'debt' => CategoryGroupTypeEnum::DEBT->value,
            'saving' => CategoryGroupTypeEnum::SAVING->value,
            default => CategoryGroupTypeEnum::EXPENSE->value,
        };
    }
}
