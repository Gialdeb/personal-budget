<?php

namespace App\Http\Requests\Transactions;

class UpdateTransactionRequest extends StoreTransactionRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'confirm_shared_review' => ['sometimes', 'boolean']];
    }
}
