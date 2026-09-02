<?php

namespace App\Http\Requests\Recurring;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.uuid' => ['required', 'uuid', 'distinct'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            'confirm' => ['accepted'],
        ];
    }
}
