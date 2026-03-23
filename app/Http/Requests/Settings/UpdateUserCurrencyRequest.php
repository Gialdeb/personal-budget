<?php

namespace App\Http\Requests\Settings;

use App\Supports\Currency\CurrencySupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'base_currency_code' => [
                'required',
                'string',
                'size:3',
                Rule::in(app(CurrencySupport::class)->codes()),
            ],
        ];
    }
}
