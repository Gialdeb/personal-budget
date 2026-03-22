<?php

namespace App\Supports\Imports;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\ImportRowStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\UserYear;
use App\Supports\CategoryHierarchy;
use Illuminate\Support\Str;

class ImportRowStatusResolver
{
    public function resolve(
        Import $import,
        array $normalizedPayload,
        ?string $fingerprint,
        ?ImportRow $currentRow = null,
        bool $duplicateInCurrentImport = false,
        bool $alreadyImported = false,
    ): array {
        $errors = [];
        $warnings = [];

        $date = $normalizedPayload['date'] ?? null;
        $type = $normalizedPayload['type'] ?? null;
        $amount = $normalizedPayload['amount'] ?? null;
        $detail = $normalizedPayload['detail'] ?? null;
        $category = $normalizedPayload['category'] ?? null;

        if (! $date) {
            $errors[] = __('imports.validation.row_date_required');
        }

        if (! $type) {
            $errors[] = __('imports.validation.row_type_required');
        }

        if (! $amount || (float) $amount <= 0) {
            $errors[] = __('imports.validation.row_amount_positive');
        }

        if (! $detail) {
            $errors[] = __('imports.validation.row_detail_required');
        }

        if ($date && ! $this->isYearManaged($import, $date)) {
            return [
                'status' => ImportRowStatusEnum::BLOCKED_YEAR->value,
                'errors' => [
                    __('imports.validation.row_year_not_managed'),
                ],
                'warnings' => $warnings,
            ];
        }

        if (! empty($errors)) {
            return [
                'status' => ImportRowStatusEnum::INVALID->value,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        if ($alreadyImported) {
            $warnings[] = __('imports.validation.already_imported');

            return [
                'status' => ImportRowStatusEnum::ALREADY_IMPORTED->value,
                'errors' => [],
                'warnings' => $warnings,
            ];
        }

        if ($duplicateInCurrentImport) {
            $warnings[] = __('imports.validation.duplicate_current_import');

            return [
                'status' => ImportRowStatusEnum::DUPLICATE_CANDIDATE->value,
                'errors' => [],
                'warnings' => $warnings,
            ];
        }

        if ($type === 'transfer') {
            $destinationAccountId = $normalizedPayload['destination_account_id'] ?? null;

            if (! $destinationAccountId) {
                $warnings[] = __('imports.validation.destination_required');

                return [
                    'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
                    'errors' => [],
                    'warnings' => $warnings,
                ];
            }

            $destinationAccount = Account::query()
                ->where('id', $destinationAccountId)
                ->where('user_id', $import->user_id)
                ->where('is_active', true)
                ->first();

            if (! $destinationAccount) {
                $errors[] = __('imports.validation.destination_invalid');

                return [
                    'status' => ImportRowStatusEnum::INVALID->value,
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            }

            if ((int) $destinationAccount->id === (int) $import->account_id) {
                $errors[] = __('imports.validation.destination_same_account');

                return [
                    'status' => ImportRowStatusEnum::INVALID->value,
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            }

            return [
                'status' => ImportRowStatusEnum::READY->value,
                'errors' => [],
                'warnings' => $warnings,
            ];
        }

        if (! $category) {
            $warnings[] = __('imports.validation.category_missing_review');

            return [
                'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
                'errors' => [],
                'warnings' => $warnings,
            ];
        }

        if (! $this->categoryExists($import, (string) $category)) {
            $warnings[] = __('imports.validation.category_unknown_review');

            return [
                'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
                'errors' => [],
                'warnings' => $warnings,
            ];
        }

        return [
            'status' => ImportRowStatusEnum::READY->value,
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    protected function isYearManaged(Import $import, string $date): bool
    {
        $year = (int) date('Y', strtotime($date));

        return UserYear::query()
            ->where('user_id', $import->user_id)
            ->where('year', $year)
            ->exists();
    }

    protected function categoryExists(Import $import, string $label): bool
    {
        $categories = Category::query()
            ->ownedBy($import->user_id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'uuid', 'parent_id', 'name', 'slug', 'group_type', 'direction_type', 'sort_order', 'is_active', 'is_selectable']);

        $normalizedLabel = mb_strtolower(trim($label));
        $slug = Str::slug($label);

        return collect(CategoryHierarchy::buildFlat($categories))
            ->contains(fn (array $category): bool => (bool) $category['is_selectable']
                && (mb_strtolower((string) $category['name']) === $normalizedLabel
                || mb_strtolower((string) $category['full_path']) === $normalizedLabel
                || (string) $category['slug'] === $slug));
    }
}
