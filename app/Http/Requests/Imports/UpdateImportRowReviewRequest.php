<?php

namespace App\Http\Requests\Imports;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImportRowReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'string'],
            'type' => ['required', 'string'],
            'amount' => ['required', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'detail' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:255'],
            'category_uuid' => ['nullable', 'uuid'],
            'reference' => ['nullable', 'string', 'max:255'],
            'tracked_item_uuid' => ['nullable', 'uuid'],
            'merchant' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'destination_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}
