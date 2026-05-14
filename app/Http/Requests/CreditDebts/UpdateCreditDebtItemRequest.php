<?php

namespace App\Http\Requests\CreditDebts;

class UpdateCreditDebtItemRequest extends StoreCreditDebtItemRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['type'][0] = 'sometimes';
        $rules['description'][0] = 'sometimes';
        $rules['total_amount'][0] = 'sometimes';
        $rules['currency_code'][0] = 'sometimes';
        $rules['account_id'][0] = 'sometimes';
        $rules['category_id'][0] = 'sometimes';
        $rules['due_date'][0] = 'sometimes';

        return $rules;
    }
}
