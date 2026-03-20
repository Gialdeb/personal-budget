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
            'category_uuid' => ['required', 'uuid'],
            'category_id' => ['nullable', 'integer'],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $categoryUuid = $this->filled('category_uuid') ? (string) $this->input('category_uuid') : null;

        $this->merge([
            'year' => $this->integer('year'),
            'month' => $this->integer('month'),
            'category_uuid' => $categoryUuid,
            'category_id' => $categoryUuid === null
                ? null
                : Category::query()
                    ->ownedBy($this->user()->id)
                    ->where('uuid', $categoryUuid)
                    ->value('id'),
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

            if ($this->filled('category_uuid') && $category === null) {
                $validator->errors()->add(
                    'category_uuid',
                    'La categoria selezionata non è disponibile.'
                );

                return;
            }

            if ($category !== null && $category->children_count > 0) {
                $validator->errors()->add(
                    'category_uuid',
                    'Le categorie padre sono di riepilogo e non possono essere modificate direttamente.'
                );
            }
        });
    }
}
