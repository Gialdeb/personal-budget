<?php

namespace App\Http\Requests\Counterparties;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CounterpartyOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('relation')?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        $monetary = in_array($action, ['store', 'update', 'returnMoney', 'compensate'], true);
        $bank = in_array($action, ['store', 'update', 'returnMoney'], true);

        return ['operation_key' => ['required', 'string', 'max:191'], 'amount' => [$monetary ? 'required' : 'nullable', 'string', 'max:30'],
            'date' => [$monetary ? 'required' : 'nullable', 'date_format:Y-m-d'], 'account_uuid' => [$bank ? 'required' : 'nullable', 'uuid'],
            'direction' => [in_array($action, ['store', 'update'], true) ? 'required' : 'nullable', Rule::in(['received', 'sent'])],
            'note' => ['nullable', 'string', 'max:5000'], 'scope' => ['nullable', Rule::in(['all', 'shared'])], 'allow_unallocated' => ['sometimes', 'boolean'],
            'allocations' => [in_array($action, ['store', 'update', 'allocations'], true) ? 'nullable' : 'prohibited', 'array', 'max:200'],
            'allocations.*.obligation_uuid' => ['required', 'uuid', 'distinct'], 'allocations.*.amount' => ['required', 'string', 'max:30'],
            'positive_uuid' => [$action === 'compensate' ? 'required' : 'nullable', 'uuid'], 'negative_uuid' => [$action === 'compensate' ? 'required' : 'nullable', 'uuid']];
    }
}
