<?php

namespace App\Http\Requests\Settings;

use App\Concerns\TrackedItemValidationRules;
use App\Models\TrackedItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateTrackedItemRequest extends FormRequest
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
        /** @var TrackedItem $trackedItem */
        $trackedItem = $this->route('trackedItem');

        return $this->trackedItemRules($this->user()->id, $trackedItem, null);
    }

    protected function prepareForValidation(): void
    {
        $slugSource = (string) ($this->input('slug') ?: $this->input('name'));
        $type = trim((string) $this->input('type', ''));

        $this->merge([
            'slug' => Str::slug($slugSource),
            'parent_uuid' => $this->filled('parent_uuid') ? (string) $this->input('parent_uuid') : null,
            'account_id' => null,
            'parent_id' => $this->filled('parent_id')
                ? (int) $this->input('parent_id')
                : ($this->filled('parent_uuid')
                    ? TrackedItem::query()->ownedBy($this->user()->id)->where('uuid', (string) $this->input('parent_uuid'))->value('id')
                    : null),
            'type' => $type !== '' ? $type : null,
            'category_uuids' => collect(
                $this->input('category_uuids', $this->input('settings.transaction_category_uuids', []))
            )
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->unique()
                ->values()
                ->all(),
            'category_ids' => $this->resolveTrackedItemCategoryIds(
                $this->user()->id,
                collect($this->input('category_uuids', $this->input('settings.transaction_category_uuids', [])))
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->unique()
                    ->values()
                    ->all(),
                null,
            ),
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
            /** @var TrackedItem $trackedItem */
            $trackedItem = $this->route('trackedItem');

            $message = $this->validateParentTrackedItem(
                $this->user()->id,
                $this->integer('parent_id') ?: null,
                $trackedItem,
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

            if (
                count($this->input('category_uuids', [])) !== count($this->input('category_ids', []))
            ) {
                $validator->errors()->add('category_uuids', 'Le categorie selezionate non sono valide.');
            }
        });
    }
}
