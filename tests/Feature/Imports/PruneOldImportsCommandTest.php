<?php

use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function makePrunableImport(array $overrides = []): Import
{
    $user = User::factory()->create();
    $createdAt = $overrides['created_at'] ?? now()->subDays(200);
    $updatedAt = $overrides['updated_at'] ?? $createdAt;
    unset($overrides['created_at'], $overrides['updated_at']);

    $import = Import::query()->create(array_merge([
        'user_id' => $user->id,
        'original_filename' => 'import-old.xlsx',
        'stored_filename' => 'imports/'.$user->id.'/import-old.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'source_type' => ImportSourceTypeEnum::XLSX,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::FAILED,
        'rows_count' => 0,
        'ready_rows_count' => 0,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 0,
        'imported_rows_count' => 0,
        'meta' => ['management_year' => 2026],
    ], $overrides));

    $import->timestamps = false;
    $import->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ])->save();

    return $import->fresh();
}

test('prune old imports removes old source files for closed imports but keeps the record within retention', function () {
    $import = makePrunableImport([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
        'status' => ImportStatusEnum::COMPLETED,
    ]);

    Storage::disk('local')->put($import->stored_filename, 'xlsx-binary');

    $this->artisan('imports:prune-old', [
        '--file-days' => 30,
        '--delete-days' => 180,
    ])->assertSuccessful();

    expect($import->fresh())
        ->not->toBeNull()
        ->and($import->fresh()->stored_filename)->toBeNull();

    Storage::disk('local')->assertMissing('imports/'.$import->user_id.'/import-old.xlsx');
});

test('prune old imports deletes failed imports and related rows when they are old and safe to remove', function () {
    $import = makePrunableImport();

    Storage::disk('local')->put($import->stored_filename, 'xlsx-binary');

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_payload' => [],
        'normalized_payload' => [],
        'errors' => [],
        'warnings' => [],
        'status' => 'invalid',
        'parse_status' => 'failed',
    ]);

    $this->artisan('imports:prune-old', [
        '--file-days' => 30,
        '--delete-days' => 180,
    ])->assertSuccessful();

    expect(Import::query()->find($import->id))->toBeNull();
    $this->assertDatabaseCount('import_rows', 0);
});
