<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'surname' => $this->surnameRules(),
            'email' => $this->emailRules($userId),
            'format_locale' => $this->formatLocaleRules(),
            'number_thousands_separator' => $this->numberThousandsSeparatorRules(),
            'number_decimal_separator' => $this->numberDecimalSeparatorRules(),
            'date_format' => $this->dateFormatRules(),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user surnames.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function surnameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user format locales.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function formatLocaleRules(): array
    {
        return [
            'required',
            'string',
            Rule::in(array_keys(config('currencies.format_locales', []))),
        ];
    }

    /**
     * Get the validation rules used to validate thousands separators.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function numberThousandsSeparatorRules(): array
    {
        return [
            'required',
            'string',
            Rule::in(array_values(config('currencies.format_preferences.thousands_separators', []))),
            'different:number_decimal_separator',
        ];
    }

    /**
     * Get the validation rules used to validate decimal separators.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function numberDecimalSeparatorRules(): array
    {
        return [
            'required',
            'string',
            Rule::in(array_values(config('currencies.format_preferences.decimal_separators', []))),
        ];
    }

    /**
     * Get the validation rules used to validate date formats.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function dateFormatRules(): array
    {
        return [
            'required',
            'string',
            Rule::in(config('currencies.format_preferences.date_formats', [])),
        ];
    }
}
