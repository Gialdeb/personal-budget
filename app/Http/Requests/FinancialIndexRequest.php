<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = [
            'year' => ['nullable', 'integer', 'between:1,9999'],
            'month' => ['nullable', Rule::in(['all', ...range(0, 12)])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];

        foreach (['status', 'type', 'scope', 'effect', 'due_bucket', 'search', 'selected'] as $key) {
            $rules[$key] = ['nullable', 'string', 'max:1000'];
        }

        foreach (['entry', 'counterparty', 'account_uuid', 'category_uuid', 'reference_uuid', 'highlight', 'template'] as $key) {
            $rules[$key] = ['nullable', 'string', Rule::when(! str_ends_with($key, '_uuid') || $this->input($key) !== 'all', ['uuid'])];
        }

        return $rules;
    }
}
