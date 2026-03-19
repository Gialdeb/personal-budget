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

        return $this->trackedItemRules($this->user()->id, $trackedItem);
    }

    protected function prepareForValidation(): void
    {
        $slugSource = (string) ($this->input('slug') ?: $this->input('name'));
        $type = trim((string) $this->input('type', ''));

        $this->merge([
            'slug' => Str::slug($slugSource),
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
            'type' => $type !== '' ? $type : null,
            'category_ids' => collect(
                $this->input('category_ids', $this->input('settings.transaction_category_ids', []))
            )
                ->filter(fn ($value): bool => is_numeric($value))
                ->map(fn ($value): int => (int) $value)
                ->unique()
                ->values()
                ->all(),
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
                $this->boolean('is_active')
            );

            if ($message !== null) {
                $validator->errors()->add('parent_id', $message);
            }
        });
    }
}
