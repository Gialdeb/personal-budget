<?php

namespace App\Concerns;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Scope;
use App\Models\UserBank;
use App\Services\Accounts\AccountBalanceConstraintService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait AccountValidationRules
{
    protected ?AccountType $resolvedAccountType = null;

    protected ?UserBank $resolvedUserBank = null;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'user_bank_id' => ['nullable', 'integer'],
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
            'settings.allow_negative_balance' => ['nullable', 'boolean'],
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
            'user_bank_id' => $this->filled('user_bank_id') ? (int) $this->input('user_bank_id') : null,
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
                'allow_negative_balance' => $this->boolean('settings.allow_negative_balance'),
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

            if ($this->filled('user_bank_id')) {
                $userBank = UserBank::query()
                    ->ownedBy($userId)
                    ->find($this->integer('user_bank_id'));

                if ($userBank === null) {
                    $validator->errors()->add('user_bank_id', 'La banca selezionata non è valida per il tuo profilo.');
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

            $balanceConstraintService = app(AccountBalanceConstraintService::class);
            $settings = $this->input('settings', []);
            $settings = is_array($settings) ? $settings : [];
            $allowNegativeBalance = $balanceConstraintService->allowsNegativeBalance($accountType, $settings);
            $openingBalance = $this->filled('opening_balance') ? (float) $this->input('opening_balance') : null;
            $currentBalance = $this->filled('current_balance') ? (float) $this->input('current_balance') : null;

            if (! $allowNegativeBalance) {
                if ($openingBalance !== null && $openingBalance < 0) {
                    $validator->errors()->add(
                        'opening_balance',
                        'Questo account non consente un saldo iniziale negativo.'
                    );
                }

                if ($currentBalance !== null && $currentBalance < 0) {
                    $validator->errors()->add(
                        'current_balance',
                        'Questo account non consente un saldo corrente negativo.'
                    );
                }
            }

            if ($accountType->code !== AccountTypeCodeEnum::CREDIT_CARD->value) {
                return;
            }

            if ($currentBalance !== null) {
                $creditLimit = Arr::get($settings, 'credit_limit');

                if (is_numeric($creditLimit) && abs(min($currentBalance, 0.0)) > (float) $creditLimit) {
                    $validator->errors()->add(
                        'current_balance',
                        'Il saldo corrente della carta supera il limite impostato.'
                    );
                }
            }

            $linkedPaymentAccountId = Arr::get($settings, 'linked_payment_account_id');

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

    public function resolveRequestedUserBank(): ?UserBank
    {
        if ($this->resolvedUserBank !== null) {
            return $this->resolvedUserBank;
        }

        if (! $this->filled('user_bank_id')) {
            return null;
        }

        $this->resolvedUserBank = UserBank::query()->find((int) $this->input('user_bank_id'));

        return $this->resolvedUserBank;
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
        $accountType = $this->resolveRequestedAccountType();

        if (! $accountType instanceof AccountType) {
            return $existingSettings === [] ? null : $existingSettings;
        }

        return app(AccountBalanceConstraintService::class)
            ->normalizeSettings($accountType, $settings, $existingSettings);
    }

    protected function normalizeNullableNumber(string $key): float|int|string|null
    {
        if (! $this->filled($key)) {
            return null;
        }

        return $this->input($key);
    }
}
