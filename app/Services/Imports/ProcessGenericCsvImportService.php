<?php

namespace App\Services\Imports;

use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Supports\Imports\GenericCsvRowNormalizer;
use App\Supports\Imports\ImportColumnMap;
use App\Supports\Imports\SimpleXlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use RuntimeException;
use Throwable;

class ProcessGenericCsvImportService
{
    public function __construct(
        protected GenericCsvRowNormalizer $normalizer,
        protected SimpleXlsxReader $xlsxReader,
    ) {}

    public function execute(Import $import, int $routeYear): Import
    {
        $path = Storage::disk('local')->path($import->stored_filename);

        if (! file_exists($path)) {
            $import->update([
                'status' => ImportStatusEnum::FAILED,
                'failed_at' => now(),
                'error_message' => 'Il file importato non è stato trovato sul disco.',
            ]);

            return $import->fresh();
        }

        try {
            [$headers, $records, $parserMeta] = $this->records($path, $import->source_type?->value);
        } catch (Throwable $exception) {
            $import->update([
                'status' => ImportStatusEnum::FAILED,
                'failed_at' => now(),
                'error_message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : __('imports.validation.file_unreadable'),
            ]);

            return $import->fresh();
        }

        $mappedHeaders = $this->mapHeaders($headers);

        $missing = array_diff(ImportColumnMap::requiredColumns(), array_values($mappedHeaders));

        if (! empty($missing)) {
            $import->update([
                'status' => ImportStatusEnum::FAILED,
                'failed_at' => now(),
                'error_message' => __('imports.validation.file_missing_columns'),
                'meta' => array_merge($import->meta ?? [], [
                    'management_year' => $routeYear,
                    'missing_columns' => array_values($missing),
                    'headers' => $headers,
                    'mapped_headers' => $mappedHeaders,
                ]),
            ]);

            return $import->fresh();
        }

        DB::transaction(function () use ($records, $mappedHeaders, $parserMeta, $import, $routeYear) {
            ImportRow::query()->where('import_id', $import->id)->delete();

            $rowsCount = 0;
            $ready = 0;
            $review = 0;
            $invalid = 0;
            $duplicate = 0;
            $seenFingerprints = [];

            foreach ($records as $record) {
                $canonicalRaw = $this->toCanonicalRow($record, $mappedHeaders);

                if ($this->isEmptyRow($canonicalRaw)) {
                    continue;
                }

                $rowsCount++;
                $sourceAccountResolution = $this->resolveAccountReference(
                    $canonicalRaw['account'] ?? null,
                    $import->user_id,
                );
                $sourceAccount = $sourceAccountResolution['account'];
                $sourceAccountId = $sourceAccount?->id;

                $normalizedResult = $this->normalizer->normalize(
                    rawRow: $canonicalRaw,
                    routeYear: $routeYear,
                    accountId: $sourceAccountId,
                    userId: $import->user_id,
                );
                $normalizedResult['normalized_payload']['account'] = $canonicalRaw['account'] ?? null;
                $normalizedResult['normalized_payload']['account_id'] = $sourceAccountId;
                $normalizedResult['normalized_payload']['account_uuid'] = $sourceAccount?->uuid;
                $normalizedResult['normalized_payload']['destination_account_id'] = $this->resolvedDestinationAccountId(
                    $canonicalRaw,
                    $import->user_id,
                );

                $status = $normalizedResult['status'];
                $fingerprint = $normalizedResult['fingerprint'];

                if ($sourceAccount === null) {
                    $normalizedResult['warnings'][] = match ($sourceAccountResolution['status']) {
                        'missing' => __('imports.validation.account_missing_review'),
                        'ambiguous' => __('imports.validation.account_ambiguous_review'),
                        default => __('imports.validation.account_unknown_review'),
                    };

                    if (empty($normalizedResult['errors'])) {
                        $status = ImportRowStatusEnum::NEEDS_REVIEW->value;
                    }
                }

                if (
                    ($normalizedResult['normalized_payload']['type'] ?? null) === 'transfer'
                    && $normalizedResult['normalized_payload']['destination_account_id'] !== null
                    && (int) $normalizedResult['normalized_payload']['destination_account_id'] !== (int) $sourceAccountId
                    && empty($normalizedResult['errors'])
                    && $sourceAccount !== null
                ) {
                    $status = ImportRowStatusEnum::READY->value;
                }

                if ($fingerprint !== null && isset($seenFingerprints[$fingerprint])) {
                    $status = ImportRowStatusEnum::DUPLICATE_CANDIDATE->value;
                    $normalizedResult['warnings'][] = __('imports.validation.duplicate_current_import');
                } elseif ($fingerprint !== null && ($duplicateSignal = $this->duplicateSignal($fingerprint, $sourceAccountId)) !== null) {
                    $status = ImportRowStatusEnum::DUPLICATE_CANDIDATE->value;
                    $normalizedResult['warnings'][] = $this->duplicateWarningMessage($duplicateSignal);
                } elseif ($fingerprint !== null && $this->alreadyImported($fingerprint, $sourceAccountId)) {
                    $status = ImportRowStatusEnum::ALREADY_IMPORTED->value;
                    $normalizedResult['warnings'][] = __('imports.validation.already_imported');
                }

                if ($fingerprint !== null) {
                    $seenFingerprints[$fingerprint] = true;
                }

                match ($status) {
                    ImportRowStatusEnum::READY->value => $ready++,
                    ImportRowStatusEnum::NEEDS_REVIEW->value => $review++,
                    ImportRowStatusEnum::ALREADY_IMPORTED->value,
                    ImportRowStatusEnum::DUPLICATE_CANDIDATE->value => $duplicate++,
                    default => $invalid++,
                };

                ImportRow::query()->create([
                    'import_id' => $import->id,
                    'row_index' => $rowsCount,
                    'status' => $status,
                    'fingerprint' => $fingerprint,
                    'raw_payload' => $canonicalRaw,
                    'normalized_payload' => $normalizedResult['normalized_payload'],
                    'errors' => $normalizedResult['errors'],
                    'warnings' => $normalizedResult['warnings'],
                    'raw_balance' => $canonicalRaw['balance'] ?? null,
                    'raw_date' => $canonicalRaw['date'] ?? null,
                    'raw_value_date' => null,
                    'raw_description' => $canonicalRaw['detail'] ?? null,
                    'raw_amount' => $canonicalRaw['amount'] ?? null,
                    'parse_status' => $this->resolveParseStatus($status),
                    'parse_error' => empty($normalizedResult['errors']) ? null : implode(' | ', $normalizedResult['errors']),
                ]);
            }

            $importStatus = match (true) {
                $rowsCount === 0 => ImportStatusEnum::FAILED,
                $invalid > 0 || $review > 0 || $duplicate > 0 => ImportStatusEnum::REVIEW_REQUIRED,
                default => ImportStatusEnum::PARSED,
            };

            $import->update([
                'status' => $importStatus,
                'rows_count' => $rowsCount,
                'ready_rows_count' => $ready,
                'review_rows_count' => $review,
                'invalid_rows_count' => $invalid,
                'duplicate_rows_count' => $duplicate,
                'imported_rows_count' => 0,
                'failed_at' => $importStatus === ImportStatusEnum::FAILED ? now() : null,
                'error_message' => $rowsCount === 0
                    ? __('imports.validation.file_no_rows')
                    : null,
                'meta' => array_merge($import->meta ?? [], [
                    'management_year' => $routeYear,
                    ...$parserMeta,
                    'mapped_headers' => $mappedHeaders,
                ]),
            ]);
        });

        return $import->fresh();
    }

    protected function mapHeaders(array $headers): array
    {
        $mapped = [];

        foreach ($headers as $header) {
            $canonical = ImportColumnMap::normalizeHeader($header);

            if ($canonical !== null) {
                $mapped[$header] = $canonical;
            }
        }

        return $mapped;
    }

    protected function toCanonicalRow(array $record, array $mappedHeaders): array
    {
        $row = [];

        foreach ($mappedHeaders as $original => $canonical) {
            $row[$canonical] = $record[$original] ?? null;
        }

        return $row;
    }

    protected function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ';';
        }

        $firstLine = fgets($handle) ?: '';

        fclose($handle);

        return substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    }

    protected function destinationAccountId(?string $value, int $userId): ?int
    {
        return $this->resolveAccountReference($value, $userId)['account']?->id;
    }

    protected function resolvedDestinationAccountId(array $canonicalRaw, int $userId): ?int
    {
        $typeLabel = (string) ($canonicalRaw['type'] ?? '');
        $normalizedType = ImportColumnMap::mapTypeLabelToInternal($typeLabel) ?? $typeLabel;

        if ($normalizedType !== 'transfer') {
            return null;
        }

        return $this->destinationAccountId(
            $canonicalRaw['destination_account'] ?? null,
            $userId,
        );
    }

    /**
     * @return array{account: Account|null, status: string}
     */
    protected function resolveAccountReference(?string $value, int $userId): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return ['account' => null, 'status' => 'missing'];
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $value, $matches) === 1) {
            $uuid = trim($matches[1]);

            if (! Str::isUuid($uuid)) {
                return ['account' => null, 'status' => 'unknown'];
            }

            $account = Account::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('uuid', $uuid)
                ->first();

            return [
                'account' => $account,
                'status' => $account instanceof Account ? 'matched' : 'unknown',
            ];
        }

        if (Str::isUuid($value)) {
            $accountByUuid = Account::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('uuid', $value)
                ->first();

            if ($accountByUuid instanceof Account) {
                return ['account' => $accountByUuid, 'status' => 'matched'];
            }
        }

        $matches = Account::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('name', $value)
            ->limit(2)
            ->get();

        return match ($matches->count()) {
            1 => ['account' => $matches->first(), 'status' => 'matched'],
            0 => ['account' => null, 'status' => 'unknown'],
            default => ['account' => null, 'status' => 'ambiguous'],
        };
    }

    /**
     * @return array{0: array<int, string>, 1: iterable<array<string, string|null>>, 2: array<string, string>}
     */
    protected function records(string $path, ?string $sourceType): array
    {
        if ($sourceType === 'xlsx' || str_ends_with(mb_strtolower($path), '.xlsx')) {
            $sheet = $this->xlsxReader->readFirstSheet($path);

            return [
                $sheet['headers'],
                $sheet['records'],
                ['parser' => 'generic_xlsx'],
            ];
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter($this->detectDelimiter($path));
        $csv->setHeaderOffset(0);

        return [
            $csv->getHeader(),
            Statement::create()->process($csv),
            [
                'parser' => 'generic_csv',
                'delimiter' => $csv->getDelimiter(),
            ],
        ];
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function resolveParseStatus(string $status): ImportRowParseStatusEnum
    {
        return match ($status) {
            ImportRowStatusEnum::INVALID->value => ImportRowParseStatusEnum::FAILED,
            default => ImportRowParseStatusEnum::PARSED,
        };
    }

    protected function alreadyImported(string $fingerprint, ?int $accountId): bool
    {
        if ($accountId === null) {
            return false;
        }

        return ImportRow::query()
            ->where('fingerprint', $fingerprint)
            ->where(function ($query) use ($accountId): void {
                $query
                    ->where('normalized_payload->account_id', $accountId)
                    ->orWhereHas('import', function ($importQuery) use ($accountId): void {
                        $importQuery->where('account_id', $accountId);
                    });
            })
            ->whereIn('status', [
                ImportRowStatusEnum::IMPORTED->value,
                ImportRowStatusEnum::ALREADY_IMPORTED->value,
            ])
            ->exists();
    }

    protected function duplicateSignal(string $fingerprint, ?int $accountId): ?string
    {
        if ($accountId === null) {
            return null;
        }

        $matchingImportRows = ImportRow::query()
            ->where('fingerprint', $fingerprint)
            ->where(function ($query) use ($accountId): void {
                $query
                    ->where('normalized_payload->account_id', $accountId)
                    ->orWhereHas('import', function ($importQuery) use ($accountId): void {
                        $importQuery->where('account_id', $accountId);
                    });
            })
            ->whereIn('status', [
                ImportRowStatusEnum::IMPORTED->value,
                ImportRowStatusEnum::ALREADY_IMPORTED->value,
            ]);

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
}
