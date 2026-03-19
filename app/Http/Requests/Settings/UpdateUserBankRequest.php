<?php

namespace App\Http\Requests\Settings;

use App\Concerns\UserBankValidationRules;
use App\Models\UserBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserBankRequest extends FormRequest
{
    use UserBankValidationRules;

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
        /** @var UserBank $userBank */
        $userBank = $this->route('userBank');

        return $this->updateUserBankRules($this->user()->id, $userBank);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareUpdateUserBankValidation();
    }

    public function withValidator(Validator $validator): void
    {
        /** @var UserBank $userBank */
        $userBank = $this->route('userBank');

        $this->validateUpdateUserBankRules($validator, $userBank);
    }
}
