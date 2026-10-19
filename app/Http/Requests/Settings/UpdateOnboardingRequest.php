<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['complete', 'skip', 'disable', 'reset', 'migrate'])],
            'section' => ['sometimes', 'string', Rule::in(['dashboard', 'transactions', 'recurring', 'shared-expenses', 'accounts', 'categories', 'planning', 'credits-debts', 'salary', 'quick-actions', 'references', 'banks', 'years', 'shared-categories', 'profile', 'security', 'appearance', 'exchange-rates', 'exports', 'support', 'notifications', 'recurring-detail', 'counterparty', 'monthly-recap', 'imports', 'import-detail', 'report-overview', 'report-categories', 'report-category-analysis', 'report-accounts', 'settings-home', 'report-hub'])],
            'quick_start_dismissed' => ['sometimes', 'boolean'],
        ];
    }
}
