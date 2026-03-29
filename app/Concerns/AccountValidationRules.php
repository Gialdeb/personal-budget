<?php

namespace App\Concerns;

use App\Enums\AccountTypeCodeEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Transaction;
use App\Models\UserBank;
use App\Services\Accounts\AccountBalanceConstraintService;
use App\Services\UserYearService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'user_bank_uuid' => ['nullable', 'uuid'],
            'user_bank_id' => ['nullable', 'integer'],
            'account_type_uuid' => ['nullable', 'uuid'],
            'account_type_id' => ['nullable', 'integer', Rule::exists(AccountType::class, 'id')],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z0-9]{15,34}$/'],
            'account_number_masked' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9*#\\-\\s]+$/'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'opening_balance_direction' => ['required', 'string', Rule::in(['positive', 'negative'])],
            'opening_balance_date' => ['nullable', 'date'],
            'current_balance' => ['nullable', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'is_manual' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_reported' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'settings' => ['nullable', 'array'],
            'settings.credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'settings.linked_payment_account_uuid' => ['nullable', 'uuid'],
            'settings.linked_payment_account_id' => ['nullable', 'integer'],
            'settings.statement_closing_day' => ['nullable', 'integer', 'between:1,31'],
            'settings.payment_day' => ['nullable', 'integer', 'between:1,31'],
            'settings.auto_pay' => ['nullable', 'boolean'],
            'settings.allow_negative_balance' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareAccountForValidation(bool $defaultIsActive = true, bool $defaultIsManual = true): void
    {
        $settings = $this->input('settings', []);
        $settings = is_array($settings) ? $settings : [];
        $userBankUuid = $this->filled('user_bank_uuid') ? (string) $this->input('user_bank_uuid') : null;
        $userBankId = $this->filled('user_bank_id') ? (int) $this->input('user_bank_id') : null;
        $accountTypeUuid = $this->filled('account_type_uuid') ? (string) $this->input('account_type_uuid') : null;
        $accountTypeId = $this->filled('account_type_id') ? (int) $this->input('account_type_id') : null;
        $linkedPaymentAccountUuid = $this->filled('settings.linked_payment_account_uuid')
            ? (string) $this->input('settings.linked_payment_account_uuid')
            : null;
        $linkedPaymentAccountId = $this->filled('settings.linked_payment_account_id')
            ? (int) $this->input('settings.linked_payment_account_id')
            : null;

        $iban = strtoupper(preg_replace('/\s+/', '', (string) $this->input('iban', '')) ?? '');
        $accountNumberMasked = trim((string) $this->input('account_number_masked', ''));
        $notes = trim((string) $this->input('notes', ''));
        $baseCurrencyCode = $this->user()?->base_currency_code ?? 'EUR';

        $this->merge([
            'user_bank_uuid' => $userBankUuid,
            'user_bank_id' => $userBankId
                ?? ($userBankUuid === null
                    ? null
                    : UserBank::query()->where('uuid', $userBankUuid)->value('id')),
            'account_type_uuid' => $accountTypeUuid,
            'account_type_id' => $accountTypeId
                ?? ($accountTypeUuid === null
                    ? null
                    : AccountType::query()->where('uuid', $accountTypeUuid)->value('id')),
            'currency' => $baseCurrencyCode,
            'iban' => $iban !== '' ? $iban : null,
            'account_number_masked' => $accountNumberMasked !== '' ? $accountNumberMasked : null,
            'opening_balance' => $this->normalizeNullableNumber('opening_balance'),
            'opening_balance_direction' => $this->filled('opening_balance_direction')
                ? (string) $this->input('opening_balance_direction')
                : 'positive',
            'opening_balance_date' => $this->normalizeNullableDate('opening_balance_date'),
            'current_balance' => $this->normalizeNullableNumber('current_balance'),
            'is_manual' => $this->has('is_manual')
                ? $this->boolean('is_manual')
                : $defaultIsManual,
            'is_active' => $this->boolean('is_active', $defaultIsActive),
            'is_reported' => $this->boolean('is_reported', true),
            'is_default' => $this->boolean('is_default', false),
            'notes' => $notes !== '' ? $notes : null,
            'settings' => [
                ...$settings,
                'credit_limit' => $this->normalizeNullableNumber('settings.credit_limit'),
                'linked_payment_account_uuid' => $linkedPaymentAccountUuid,
                'linked_payment_account_id' => $linkedPaymentAccountId
                    ?? ($linkedPaymentAccountUuid === null
                        ? null
                        : Account::query()->where('uuid', $linkedPaymentAccountUuid)->value('id')),
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
            if ($this->filled('user_bank_id')) {
                $userBank = UserBank::query()
                    ->ownedBy($userId)
                    ->find($this->integer('user_bank_id'));

                if ($userBank === null) {
                    $validator->errors()->add('user_bank_id', 'La banca selezionata non è valida per il tuo profilo.');
                }
            } elseif ($this->filled('user_bank_uuid')) {
                $validator->errors()->add('user_bank_id', 'La banca selezionata non è valida per il tuo profilo.');
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

            if ($this->boolean('is_default') && ! $this->boolean('is_active')) {
                $validator->errors()->add(
                    'is_default',
                    __('accounts.validation.default_account_must_be_active')
                );
            }

            $balanceConstraintService = app(AccountBalanceConstraintService::class);
            $settings = $this->input('settings', []);
            $settings = is_array($settings) ? $settings : [];
            $allowNegativeBalance = $balanceConstraintService->allowsNegativeBalance($accountType, $settings);
            $openingBalance = $this->filled('opening_balance') ? (float) $this->input('opening_balance') : null;
            $openingBalanceDirection = (string) $this->input('opening_balance_direction', 'positive');
            $signedOpeningBalance = $openingBalance === null
                ? null
                : ($openingBalanceDirection === 'negative' ? $openingBalance * -1 : $openingBalance);
            $openingBalanceDate = $this->input('opening_balance_date');
            $currentBalance = $this->filled('current_balance') ? (float) $this->input('current_balance') : null;
            $hasOpeningBalance = $openingBalance !== null && abs($openingBalance) > 0.00001;

            if ($hasOpeningBalance && ! is_string($openingBalanceDate)) {
                $validator->errors()->add(
                    'opening_balance_date',
                    __('accounts.validation.opening_balance_date_required')
                );
            }

            if (is_string($openingBalanceDate)) {
                $today = now()->toDateString();

                if ($openingBalanceDate > $today) {
                    $validator->errors()->add(
                        'opening_balance_date',
                        __('accounts.validation.opening_balance_date_not_future', [
                            'date' => $this->formatOpeningBalanceValidationDate($today),
                        ])
                    );
                } else {
                    try {
                        app(UserYearService::class)->ensureDateYearIsOpen(
                            $this->user(),
                            $openingBalanceDate,
                            'opening_balance_date',
                        );
                    } catch (ValidationException $exception) {
                        foreach (($exception->errors()['opening_balance_date'] ?? []) as $message) {
                            $validator->errors()->add('opening_balance_date', $message);
                        }
                    }
                }
            }

            if ($hasOpeningBalance && is_string($openingBalanceDate) && $account !== null) {
                $firstOperationalTransactionDate = $this->firstOperationalTransactionDate($account);

                if (
                    $firstOperationalTransactionDate !== null
                    && $openingBalanceDate > $firstOperationalTransactionDate
                ) {
                    $validator->errors()->add(
                        'opening_balance_date',
                        __('accounts.validation.opening_balance_date_after_first_transaction', [
                            'date' => $this->formatOpeningBalanceValidationDate($firstOperationalTransactionDate),
                        ])
                    );
                }
            }

            if ($accountType->code === AccountTypeCodeEnum::CASH_ACCOUNT->value) {
                if ($signedOpeningBalance !== null && $signedOpeningBalance < 0) {
                    $validator->errors()->add(
                        'opening_balance',
                        'Questo account non consente un saldo iniziale negativo.'
                    );
                }

            }

            if (! $allowNegativeBalance) {
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

                return;
            }

            if ($linkedPaymentAccount->accountType?->code === AccountTypeCodeEnum::CASH_ACCOUNT->value) {
                $validator->errors()->add(
                    'settings.linked_payment_account_id',
                    'Il conto di addebito non può essere la cassa contanti.'
                );

                return;
            }

            $requestedUserBank = $this->resolveRequestedUserBank();

            if ($requestedUserBank !== null && $linkedPaymentAccount->user_bank_id !== $requestedUserBank->id) {
                $validator->errors()->add(
                    'settings.linked_payment_account_id',
                    'Il conto di addebito deve appartenere alla stessa banca selezionata per la carta di credito.'
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

        $accountType = $this->resolveRequestedAccountType();

        if ($accountType?->code === AccountTypeCodeEnum::CASH_ACCOUNT->value) {
            return null;
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
        unset($settings['linked_payment_account_uuid']);
        $existingSettings = is_array($existingSettings) ? $existingSettings : [];
        $accountType = $this->resolveRequestedAccountType();

        if (! $accountType instanceof AccountType) {
            return $existingSettings === [] ? null : $existingSettings;
        }

        if (
            (string) $this->input('opening_balance_direction', 'positive') === 'negative'
            && $accountType->code !== AccountTypeCodeEnum::CASH_ACCOUNT->value
        ) {
            $settings['allow_negative_balance'] = true;
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

    protected function normalizeNullableDate(string $key): ?string
    {
        if (! $this->filled($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value !== '' ? $value : null;
    }

    public function openingBalanceAmount(): ?float
    {
        if (! $this->filled('opening_balance')) {
            return null;
        }

        return round((float) $this->input('opening_balance'), 2);
    }

    public function openingBalanceDirection(): string
    {
        return (string) $this->input('opening_balance_direction', 'positive');
    }

    public function openingBalanceDate(): ?string
    {
        if (! $this->hasOpeningBalanceAmount()) {
            return null;
        }

        return $this->input('opening_balance_date');
    }

    public function hasOpeningBalanceAmount(): bool
    {
        $amount = $this->openingBalanceAmount();

        return $amount !== null && abs($amount) > 0.00001;
    }

    protected function firstOperationalTransactionDate(Account $account): ?string
    {
        $firstTransactionDate = Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->value('transaction_date');

        if ($firstTransactionDate instanceof \DateTimeInterface) {
            return Carbon::instance($firstTransactionDate)->toDateString();
        }

        return is_string($firstTransactionDate) ? Carbon::parse($firstTransactionDate)->toDateString() : null;
    }

    protected function formatOpeningBalanceValidationDate(string $date): string
    {
        $locale = app()->getLocale();
        $format = str_starts_with($locale, 'en') ? 'M j, Y' : 'd M Y';

        return Carbon::parse($date)
            ->locale($locale)
            ->translatedFormat($format);
    }
}
