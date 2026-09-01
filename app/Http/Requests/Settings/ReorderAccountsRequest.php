<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderAccountsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'account_type_uuid' => ['required', 'uuid', Rule::exists('account_types', 'uuid')],
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*.uuid' => ['required', 'uuid', 'distinct', Rule::exists('accounts', 'uuid')],
            'accounts.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
