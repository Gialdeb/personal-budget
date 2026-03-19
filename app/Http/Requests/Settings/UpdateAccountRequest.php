<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AccountValidationRules;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends FormRequest
{
    use AccountValidationRules;

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
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return $this->accountRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareAccountForValidation();
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Account $account */
        $account = $this->route('account');

        $this->validateAccountRules($validator, $this->user()->id, $account);
    }
}
