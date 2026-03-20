<?php

namespace App\Services\Imports;

use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Models\ImportRow;
use App\Supports\Imports\GenericCsvRowNormalizer;
use App\Supports\Imports\ImportColumnMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;
use Throwable;

class ProcessGenericCsvImportService
{
    public function __construct(
        protected GenericCsvRowNormalizer $normalizer,
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
            $csv = Reader::createFromPath($path, 'r');
            $csv->setDelimiter($this->detectDelimiter($path));
            $csv->setHeaderOffset(0);
        } catch (Throwable) {
            $import->update([
                'status' => ImportStatusEnum::FAILED,
                'failed_at' => now(),
                'error_message' => 'Il file CSV non può essere letto.',
            ]);

            return $import->fresh();
        }

        $headers = $csv->getHeader();
        $mappedHeaders = $this->mapHeaders($headers);

        $missing = array_diff(ImportColumnMap::requiredColumns(), array_values($mappedHeaders));

        if (! empty($missing)) {
            $import->update([
                'status' => ImportStatusEnum::FAILED,
                'failed_at' => now(),
                'error_message' => 'Mancano colonne obbligatorie nel file CSV.',
                'meta' => array_merge($import->meta ?? [], [
                    'management_year' => $routeYear,
                    'missing_columns' => array_values($missing),
                    'headers' => $headers,
                    'mapped_headers' => $mappedHeaders,
                ]),
            ]);

            return $import->fresh();
        }

        DB::transaction(function () use ($csv, $mappedHeaders, $import, $routeYear) {
            ImportRow::query()->where('import_id', $import->id)->delete();

            $statement = Statement::create();
            $records = $statement->process($csv);

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

                $normalizedResult = $this->normalizer->normalize(
                    rawRow: $canonicalRaw,
                    routeYear: $routeYear,
                    accountId: $import->account_id,
                    userId: $import->user_id,
                );

                $status = $normalizedResult['status'];
                $fingerprint = $normalizedResult['fingerprint'];

                if ($fingerprint !== null && isset($seenFingerprints[$fingerprint])) {
                    $status = ImportRowStatusEnum::DUPLICATE_CANDIDATE->value;
                    $normalizedResult['warnings'][] = 'Questa riga è duplicata rispetto a un’altra riga dello stesso file.';
                } elseif ($fingerprint !== null && $this->alreadyImported($fingerprint, $import->account_id)) {
                    $status = ImportRowStatusEnum::ALREADY_IMPORTED->value;
                    $normalizedResult['warnings'][] = 'Questa riga sembra già importata in precedenza.';
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
                'failed_at' => null,
                'error_message' => null,
                'meta' => array_merge($import->meta ?? [], [
                    'management_year' => $routeYear,
                    'parser' => 'generic_csv',
                    'delimiter' => $csv->getDelimiter(),
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
            ->whereHas('import', function ($query) use ($accountId): void {
                $query->where('account_id', $accountId);
            })
            ->whereIn('status', [
                ImportRowStatusEnum::IMPORTED->value,
                ImportRowStatusEnum::ALREADY_IMPORTED->value,
            ])
            ->exists();
    }
}
