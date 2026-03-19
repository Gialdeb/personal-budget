<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'between:1900,2200',
                Rule::unique('user_years', 'year')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.required' => "Inserisci l'anno di gestione.",
            'year.integer' => "L'anno deve essere un numero intero.",
            'year.between' => 'Inserisci un anno valido tra 1900 e 2200.',
            'year.unique' => 'Questo anno di gestione è già presente.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->integer('year'),
        ]);
    }
}
