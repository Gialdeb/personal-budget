<?php

namespace App\Http\Requests\Recurring;

use App\Enums\RecurringEntryTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PauseRecurringEntryRequest extends FormRequest
{
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
        return [
            'periods' => [
                $this->route('recurringEntry')?->entry_type === RecurringEntryTypeEnum::RECURRING ? 'required' : 'nullable',
                'integer', 'min:1', 'max:1000',
            ],
        ];
    }
}
