<?php

namespace App\Concerns;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait AccountValidationRules
{
    protected ?AccountType $resolvedAccountType = null;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'bank_id' => ['nullable', 'integer', Rule::exists(Bank::class, 'id')],
            'account_type_id' => ['required', 'integer', Rule::exists(AccountType::class, 'id')],
            'scope_id' => ['nullable', 'integer'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z0-9]{15,34}$/'],
            'account_number_masked' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9*#\\-\\s]+$/'],
            'opening_balance' => ['nullable', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'current_balance' => ['nullable', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'is_manual' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'settings' => ['nullable', 'array'],
            'settings.credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'settings.linked_payment_account_id' => ['nullable', 'integer'],
            'settings.statement_closing_day' => ['nullable', 'integer', 'between:1,31'],
            'settings.payment_day' => ['nullable', 'integer', 'between:1,31'],
            'settings.auto_pay' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareAccountForValidation(bool $defaultIsActive = true): void
    {
        $settings = $this->input('settings', []);
        $settings = is_array($settings) ? $settings : [];

        $iban = strtoupper(preg_replace('/\s+/', '', (string) $this->input('iban', '')) ?? '');
        $accountNumberMasked = trim((string) $this->input('account_number_masked', ''));
        $notes = trim((string) $this->input('notes', ''));

        $this->merge([
            'bank_id' => $this->filled('bank_id') ? (int) $this->input('bank_id') : null,
            'account_type_id' => $this->filled('account_type_id') ? (int) $this->input('account_type_id') : null,
            'scope_id' => $this->filled('scope_id') ? (int) $this->input('scope_id') : null,
            'currency' => strtoupper(trim((string) $this->input('currency', 'EUR'))),
            'iban' => $iban !== '' ? $iban : null,
            'account_number_masked' => $accountNumberMasked !== '' ? $accountNumberMasked : null,
            'opening_balance' => $this->normalizeNullableNumber('opening_balance'),
            'current_balance' => $this->normalizeNullableNumber('current_balance'),
            'is_manual' => $this->boolean('is_manual'),
            'is_active' => $this->boolean('is_active', $defaultIsActive),
            'notes' => $notes !== '' ? $notes : null,
            'settings' => [
                ...$settings,
                'credit_limit' => $this->normalizeNullableNumber('settings.credit_limit'),
                'linked_payment_account_id' => $this->filled('settings.linked_payment_account_id')
                    ? (int) $this->input('settings.linked_payment_account_id')
                    : null,
                'statement_closing_day' => $this->filled('settings.statement_closing_day')
                    ? (int) $this->input('settings.statement_closing_day')
                    : null,
                'payment_day' => $this->filled('settings.payment_day')
                    ? (int) $this->input('settings.payment_day')
                    : null,
                'auto_pay' => $this->boolean('settings.auto_pay'),
            ],
        ]);
    }

    protected function validateAccountRules(Validator $validator, int $userId, ?Account $account = null): void
    {
        $validator->after(function (Validator $validator) use ($userId, $account): void {
            if ($this->filled('scope_id')) {
                $scope = Scope::query()
                    ->where('user_id', $userId)
                    ->find($this->integer('scope_id'));

                if ($scope === null) {
                    $validator->errors()->add('scope_id', 'Lo scope selezionato non è valido.');
                }
            }

            $accountType = $this->resolveRequestedAccountType();

            if ($accountType === null) {
                $validator->errors()->add('account_type_id', 'Il tipo account selezionato non è valido.');

                return;
            }

            if ($accountType->code === AccountTypeCodeEnum::CASH_ACCOUNT->value) {
                if ($this->filled('iban')) {
                    $validator->errors()->add('iban', 'La cassa contanti non può avere un IBAN.');
                }

                if ($this->filled('account_number_masked')) {
                    $validator->errors()->add(
                        'account_number_masked',
                        'La cassa contanti non può avere un numero account o carta.'
                    );
                }
            }

            if ($accountType->code !== AccountTypeCodeEnum::CREDIT_CARD->value) {
                return;
            }

            $linkedPaymentAccountId = Arr::get($this->input('settings', []), 'linked_payment_account_id');

            if ($linkedPaymentAccountId === null) {
                return;
            }

            $linkedPaymentAccount = Account::query()
                ->ownedBy($userId)
                ->with('accountType:id,code')
                ->find((int) $linkedPaymentAccountId);

            if ($linkedPaymentAccount === null) {
                $validator->errors()->add(
                    'settings.linked_payment_account_id',
                    'Il conto di addebito selezionato non è valido.'
                );

                return;
            }

            if ($account !== null && $linkedPaymentAccount->id === $account->id) {
                $validator->errors()->add(
                    'settings.linked_payment_account_id',
                    'Una carta di credito non può essere collegata a sé stessa come conto di addebito.'
                );

                return;
            }

            if ($linkedPaymentAccount->accountType?->code === AccountTypeCodeEnum::CREDIT_CARD->value) {
                $validator->errors()->add(
                    'settings.linked_payment_account_id',
                    'Il conto di addebito deve essere un account diverso da una carta di credito.'
                );
            }
        });
    }

    public function resolveRequestedAccountType(): ?AccountType
    {
        if ($this->resolvedAccountType !== null) {
            return $this->resolvedAccountType;
        }

        if (! $this->filled('account_type_id')) {
            return null;
        }

        $this->resolvedAccountType = AccountType::query()->find((int) $this->input('account_type_id'));

        return $this->resolvedAccountType;
    }

    /**
     * @param  array<string, mixed>|null  $existingSettings
     * @return array<string, mixed>|null
     */
    public function normalizedSettings(?array $existingSettings = null): ?array
    {
        $settings = $this->validated('settings', []);
        $settings = is_array($settings) ? $settings : [];
        $existingSettings = is_array($existingSettings) ? $existingSettings : [];

        $creditCardKeys = [
            'credit_limit',
            'linked_payment_account_id',
            'statement_closing_day',
            'payment_day',
            'auto_pay',
        ];

        $accountType = $this->resolveRequestedAccountType();

        if ($accountType?->code === AccountTypeCodeEnum::CREDIT_CARD->value) {
            $normalized = $existingSettings;

            foreach ($creditCardKeys as $key) {
                if (! array_key_exists($key, $settings)) {
                    continue;
                }

                $value = $settings[$key];

                if ($key === 'auto_pay') {
                    $normalized[$key] = (bool) $value;

                    continue;
                }

                if ($value === null || $value === '') {
                    unset($normalized[$key]);

                    continue;
                }

                $normalized[$key] = $key === 'credit_limit'
                    ? round((float) $value, 2)
                    : $value;
            }

            return $normalized === [] ? null : $normalized;
        }

        $normalized = $existingSettings;

        foreach ($creditCardKeys as $key) {
            unset($normalized[$key]);
        }

        foreach ($settings as $key => $value) {
            if (in_array($key, $creditCardKeys, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                unset($normalized[$key]);

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized === [] ? null : $normalized;
    }

    protected function normalizeNullableNumber(string $key): float|int|string|null
    {
        if (! $this->filled($key)) {
            return null;
        }

        return $this->input($key);
    }
}
