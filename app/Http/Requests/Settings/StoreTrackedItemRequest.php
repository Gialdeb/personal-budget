<?php

namespace App\Http\Requests\Settings;

use App\Concerns\TrackedItemValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
        return $this->trackedItemRules($this->user()->id);
    }

    protected function prepareForValidation(): void
    {
        $slugSource = (string) ($this->input('slug') ?: $this->input('name'));
        $type = trim((string) $this->input('type', ''));

        $this->merge([
            'slug' => Str::slug($slugSource),
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
            'type' => $type !== '' ? $type : null,
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
                $this->boolean('is_active')
            );

            if ($message !== null) {
                $validator->errors()->add('parent_id', $message);
            }
        });
    }
}
