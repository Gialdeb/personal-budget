<?php

namespace App\Http\Requests\SharedExpenses;

use Illuminate\Foundation\Http\FormRequest;

class ResolveSharedExpenseReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reference_uuid' => ['required', 'uuid'], 'confirm_reference' => ['required', 'accepted']];
    }
}
