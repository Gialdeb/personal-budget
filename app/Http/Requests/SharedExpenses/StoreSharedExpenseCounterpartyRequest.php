<?php

namespace App\Http\Requests\SharedExpenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSharedExpenseCounterpartyRequest extends FormRequest
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
            'name' => ['required_without:tracked_item_uuid', 'nullable', 'string', 'max:150'],
            'tracked_item_uuid' => ['nullable', 'uuid'],
        ];
    }
}
