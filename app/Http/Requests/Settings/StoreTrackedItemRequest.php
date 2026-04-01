<?php

namespace App\Http\Requests\Settings;

use App\Concerns\TrackedItemValidationRules;
use App\Models\Category;
use App\Models\TrackedItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTrackedItemRequest extends FormRequest
{
    use TrackedItemValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->trackedItemRules($this->user()->id, null, null);
    }

    protected function prepareForValidation(): void
    {
        $type = trim((string) $this->input('type', ''));
        $rawCategoryUuids = collect(
            $this->input('category_uuids', $this->input('settings.transaction_category_uuids', []))
        )
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
        $rawCategoryIds = collect(
            $this->input('category_ids', $this->input('settings.transaction_category_ids', []))
        )
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
        $resolvedCategoryIds = $rawCategoryIds !== []
            ? Category::query()
                ->ownedBy($this->user()->id)
                ->whereIn('id', $rawCategoryIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all()
            : $this->resolveTrackedItemCategoryIds($this->user()->id, $rawCategoryUuids, null);
        $resolvedCategoryUuids = $rawCategoryUuids !== []
            ? $rawCategoryUuids
            : ($resolvedCategoryIds !== []
                ? Category::query()
                    ->ownedBy($this->user()->id)
                    ->whereIn('id', $resolvedCategoryIds)
                    ->pluck('uuid')
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->values()
                    ->all()
                : []);

        $this->merge([
            'slug' => $this->normalizeTrackedItemSlug(
                $this->user()->id,
                (string) $this->input('name'),
                $this->input('slug'),
            ),
            'parent_uuid' => $this->filled('parent_uuid') ? (string) $this->input('parent_uuid') : null,
            'account_id' => null,
            'parent_id' => $this->filled('parent_id')
                ? (int) $this->input('parent_id')
                : ($this->filled('parent_uuid')
                    ? TrackedItem::query()->ownedBy($this->user()->id)->where('uuid', (string) $this->input('parent_uuid'))->value('id')
                    : null),
            'type' => $type !== '' ? $type : null,
            'category_uuids' => $resolvedCategoryUuids,
            'category_ids' => $resolvedCategoryIds,
            'settings' => [
                'transaction_group_keys' => collect($this->input('settings.transaction_group_keys', []))
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->values()
                    ->all(),
            ],
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = $this->validateParentTrackedItem(
                $this->user()->id,
                $this->integer('parent_id') ?: null,
                null,
                $this->boolean('is_active'),
                null,
            );

            if (($this->filled('parent_uuid') || $this->filled('parent_id')) && ! $this->integer('parent_id')) {
                $validator->errors()->add('parent_id', "L'elemento padre selezionato non è valido.");

                return;
            }

            if ($message !== null) {
                $validator->errors()->add('parent_id', $message);
            }

            $nameMessage = $this->validateTrackedItemNameUniqueness(
                $this->user()->id,
                (string) $this->input('name'),
            );

            if ($nameMessage !== null) {
                $validator->errors()->add('name', $nameMessage);
            }

            if (
                count($this->input('category_uuids', [])) !== count($this->input('category_ids', []))
            ) {
                $validator->errors()->add('category_uuids', 'Le categorie selezionate non sono valide.');
            }
        });
    }
}
