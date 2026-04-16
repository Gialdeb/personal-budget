<?php

namespace App\Services\Communication\ContextResolvers;

use App\Contracts\CommunicationContextResolverInterface;
use App\Models\Import;
use App\Support\Banks\BankNamePresenter;

class ImportCommunicationContextResolver implements CommunicationContextResolverInterface
{
    public function supports(string $contextType): bool
    {
        return $contextType === 'import';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(object $model): array
    {
        /** @var Import $model */
        $model->loadMissing(['account.bank', 'importFormat.bank', 'user']);

        return [
            'import' => [
                'uuid' => $model->uuid,
                'filename' => $model->original_filename,
                'status' => $model->status?->value ?? (string) $model->status,
                'rows_count' => $model->rows_count,
                'imported_rows_count' => $model->imported_rows_count,
                'review_rows_count' => $model->review_rows_count,
                'invalid_rows_count' => $model->invalid_rows_count,
                'duplicate_rows_count' => $model->duplicate_rows_count,
                'account_name' => $model->account?->name,
                'bank_name' => $model->account ? BankNamePresenter::forAccount($model->account) : null,
                'user_uuid' => $model->user?->uuid,
                'user_email' => $model->user?->email,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, example: string|null}>
     */
    public function availableVariables(): array
    {
        return [
            ['key' => 'import.uuid', 'label' => 'Import UUID', 'example' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'],
            ['key' => 'import.filename', 'label' => 'Filename', 'example' => 'movements-march.csv'],
            ['key' => 'import.status', 'label' => 'Import status', 'example' => 'completed'],
            ['key' => 'import.rows_count', 'label' => 'Rows count', 'example' => '120'],
            ['key' => 'import.imported_rows_count', 'label' => 'Imported rows', 'example' => '118'],
            ['key' => 'import.review_rows_count', 'label' => 'Review rows', 'example' => '1'],
            ['key' => 'import.invalid_rows_count', 'label' => 'Invalid rows', 'example' => '1'],
            ['key' => 'import.duplicate_rows_count', 'label' => 'Duplicate rows', 'example' => '0'],
            ['key' => 'import.account_name', 'label' => 'Account name', 'example' => 'Main account'],
            ['key' => 'import.bank_name', 'label' => 'Bank name', 'example' => 'Fineco'],
            ['key' => 'import.user_uuid', 'label' => 'User UUID', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
            ['key' => 'import.user_email', 'label' => 'User email', 'example' => 'giuseppe@example.com'],
        ];
    }
}
