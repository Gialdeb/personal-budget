<?php

namespace App\Http\Requests\Settings;

use App\Concerns\CategoryValidationRules;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    use CategoryValidationRules;

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
        return $this->categoryRules($this->user()->id);
    }

    protected function prepareForValidation(): void
    {
        $slugSource = (string) ($this->input('slug') ?: $this->input('name'));

        $this->merge([
            'slug' => Str::slug($slugSource),
            'parent_uuid' => $this->filled('parent_uuid') ? (string) $this->input('parent_uuid') : null,
            'parent_id' => $this->filled('parent_uuid')
                ? Category::query()->where('uuid', (string) $this->input('parent_uuid'))->value('id')
                : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
            'is_active' => $this->boolean('is_active', true),
            'is_selectable' => $this->boolean('is_selectable', true),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = $this->validateParentCategory(
                $this->user()->id,
                $this->integer('parent_id') ?: null,
                null,
                $this->boolean('is_active')
            );

            if ($this->filled('parent_uuid') && ! $this->filled('parent_id')) {
                $validator->errors()->add('parent_uuid', 'La categoria padre selezionata non è valida.');

                return;
            }

            if ($message !== null) {
                $validator->errors()->add('parent_uuid', $message);
            }
        });
    }
}
