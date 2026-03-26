<?php

namespace App\Http\Requests\Transactions;

use App\Models\Account;
use App\Services\Accounts\AccessibleAccountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $daysInMonth = CarbonImmutable::create($routeYear, $routeMonth, 1)->daysInMonth;

        return [
            'transaction_day' => ['required', 'integer', 'between:1,'.$daysInMonth],
            'transaction_date' => ['nullable', 'date'],
            'account_uuid' => ['required', 'uuid'],
            'account_id' => ['nullable', 'integer'],
            'desired_balance' => ['required', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $transactionDay = null;

        if ($this->filled('transaction_day')) {
            $transactionDay = (int) $this->input('transaction_day');
        } elseif ($this->filled('transaction_date')) {
            try {
                $transactionDay = CarbonImmutable::parse((string) $this->input('transaction_date'))->day;
            } catch (\Throwable) {
                $transactionDay = null;
            }
        }

        $accountUuid = $this->filled('account_uuid') ? (string) $this->input('account_uuid') : null;

        $this->merge([
            'transaction_day' => $transactionDay,
            'transaction_date' => $transactionDay !== null
                ? sprintf('%04d-%02d-%02d', $routeYear, $routeMonth, $transactionDay)
                : null,
            'account_uuid' => $accountUuid,
            'account_id' => $this->filled('account_id')
                ? (int) $this->input('account_id')
                : ($accountUuid !== null
                    ? Account::query()->where('uuid', $accountUuid)->value('id')
                    : null),
            'desired_balance' => $this->filled('desired_balance')
                ? round((float) $this->input('desired_balance'), 2)
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has(['transaction_day', 'account_uuid', 'desired_balance'])) {
                return;
            }

            $accountId = $this->integer('account_id');

            if ($accountId <= 0) {
                $validator->errors()->add('account_uuid', __('transactions.validation.account_unavailable'));

                return;
            }

            $account = app(AccessibleAccountsQuery::class)->findAccessibleAccount(
                $this->user(),
                $accountId,
                true
            );

            if (! $account instanceof Account) {
                $validator->errors()->add('account_uuid', __('transactions.validation.account_read_only'));
            }
        });
    }
}
