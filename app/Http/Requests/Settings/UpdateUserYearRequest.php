<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserYearRequest extends FormRequest
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
            'is_closed' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_closed.required' => "Specifica se l'anno deve essere aperto o chiuso.",
            'is_closed.boolean' => 'Il valore di apertura/chiusura non è valido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_closed' => $this->boolean('is_closed'),
        ]);
    }
}
