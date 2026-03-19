<?php

namespace App\Http\Requests\BudgetPlanning;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBudgetCellRequest extends FormRequest
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
            'month' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query
                        ->where('user_id', $this->user()->id)
                        ->where('is_selectable', true)
                        ->where('is_active', true);
                }),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->integer('year'),
            'month' => $this->integer('month'),
            'category_id' => $this->integer('category_id'),
            'amount' => round((float) $this->input('amount', 0), 2),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = Category::query()
                ->ownedBy($this->user()->id)
                ->withCount('children')
                ->find($this->integer('category_id'));

            if ($category !== null && $category->children_count > 0) {
                $validator->errors()->add(
                    'category_id',
                    'Le categorie padre sono di riepilogo e non possono essere modificate direttamente.'
                );
            }
        });
    }
}
