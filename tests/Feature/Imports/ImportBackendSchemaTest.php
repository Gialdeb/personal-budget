<?php

use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\Schema;

test('imports table exposes review and lifecycle columns', function () {
    expect(Schema::hasColumns('imports', [
        'import_format_id',
        'rows_count',
        'ready_rows_count',
        'review_rows_count',
        'invalid_rows_count',
        'duplicate_rows_count',
        'imported_rows_count',
        'rolled_back_at',
        'completed_at',
        'failed_at',
        'meta',
    ]))->toBeTrue();
});

test('import rows table exposes functional status and deduplication columns', function () {
    expect(Schema::hasColumns('import_rows', [
        'status',
        'transaction_id',
        'fingerprint',
        'normalized_payload',
        'errors',
        'warnings',
        'rolled_back_at',
        'imported_at',
    ]))->toBeTrue();
});

test('import models cast the new backend fields', function () {
    $import = new Import;
    $row = new ImportRow;

    expect($import->getCasts())
        ->toMatchArray([
            'status' => ImportStatusEnum::class,
            'imported_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'rows_count' => 'integer',
            'ready_rows_count' => 'integer',
            'review_rows_count' => 'integer',
            'invalid_rows_count' => 'integer',
            'duplicate_rows_count' => 'integer',
            'imported_rows_count' => 'integer',
            'meta' => 'array',
        ]);

    expect($row->getCasts())
        ->toMatchArray([
            'parse_status' => ImportRowParseStatusEnum::class,
            'status' => ImportRowStatusEnum::class,
            'normalized_payload' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'rolled_back_at' => 'datetime',
            'imported_at' => 'datetime',
        ]);
});

test('import status enums expose the review and rollback states', function () {
    expect(ImportStatusEnum::ROLLED_BACK->value)->toBe('rolled_back');

    expect(array_map(
        static fn (ImportRowStatusEnum $case): string => $case->value,
        ImportRowStatusEnum::cases()
    ))->toBe([
        'parsed',
        'ready',
        'needs_review',
        'invalid',
        'blocked_year',
        'duplicate_candidate',
        'already_imported',
        'imported',
        'skipped',
        'rolled_back',
    ]);
});
