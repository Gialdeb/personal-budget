<?php

namespace App\Http\Requests\BudgetPlanning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyBudgetPlanningYearRequest extends FormRequest
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
                Rule::exists('user_years', 'year')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->integer('year'),
        ]);
    }
}
