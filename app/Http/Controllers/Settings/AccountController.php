<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\AccountTypeCodeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAccountRequest;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\User;
use App\Models\UserBank;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Accounts\AccountBalanceConstraintService;
use App\Services\Accounts\AccountOpeningBalanceService;
use App\Supports\Currency\CurrencySupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        protected AccountOpeningBalanceService $accountOpeningBalanceService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected CurrencySupport $currencySupport,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Accounts', $payload);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isProtectedCashAccount = ($request->resolveRequestedAccountType()?->code === AccountTypeCodeEnum::CASH_ACCOUNT->value)
            && ($validated['name'] ?? null) === 'Cassa contanti';
        $userBank = $request->resolveRequestedUserBank();
        unset($validated['current_balance'], $validated['opening_balance_direction']);
        if ($isProtectedCashAccount) {
            $validated['is_active'] = true;
        }

        DB::transaction(function () use ($request, $validated, $userBank, $isProtectedCashAccount): void {
            $account = Account::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
                'user_bank_id' => $userBank?->id,
                'bank_id' => $userBank?->bank_id,
                'currency' => $validated['currency'],
                'currency_code' => $validated['currency'],
                'opening_balance' => 0,
                'opening_balance_date' => $request->openingBalanceDate(),
                'current_balance' => 0,
                'settings' => $request->normalizedSettings(),
            ]);

            $shouldAutoAssignDefault = $this->shouldAutoAssignDefaultAccount($request->user(), $account);

            $account->forceFill([
                'is_default' => $shouldAutoAssignDefault
                    || ((bool) ($validated['is_default'] ?? false) && (bool) $account->is_active),
            ])->save();

            $this->syncDefaultAccountForUser($request->user(), $account);

            $this->accountOpeningBalanceService->sync(
                $account,
                $request->openingBalanceAmount(),
                $isProtectedCashAccount ? 'positive' : $request->openingBalanceDirection(),
                $request->openingBalanceDate(),
                $request->user(),
            );
        });

        return to_route('accounts.edit')->with('success', __('accounts.flash.created'));
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $account = $this->ownedAccount($request, $account);
        $validated = $request->validated();
        $isProtectedCashAccount = $account->loadMissing('accountType')->isProtectedCashAccount();
        unset($validated['current_balance']);
        unset($validated['currency_code']);
        unset($validated['opening_balance_direction']);
        if ($isProtectedCashAccount) {
            unset($validated['account_type_id'], $validated['account_type_uuid']);
            $validated['is_active'] = true;
        }
        $userBank = $request->resolveRequestedUserBank();

        DB::transaction(function () use ($request, $account, $validated, $userBank, $isProtectedCashAccount): void {
            $account->fill([
                ...$validated,
                'user_bank_id' => $userBank?->id,
                'bank_id' => $userBank?->bank_id,
                'currency' => $validated['currency'],
                'currency_code' => $validated['currency'],
                'opening_balance_date' => $request->openingBalanceDate(),
                'settings' => $request->normalizedSettings($account->settings),
            ]);

            if ($isProtectedCashAccount) {
                $account->is_active = true;
            }

            $account->save();

            $account->forceFill([
                'is_default' => (bool) ($validated['is_default'] ?? false) && (bool) $account->is_active,
            ])->save();

            $this->syncDefaultAccountForUser($request->user(), $account);

            $this->accountOpeningBalanceService->sync(
                $account,
                $request->openingBalanceAmount(),
                $isProtectedCashAccount ? 'positive' : $request->openingBalanceDirection(),
                $request->openingBalanceDate(),
                $request->user(),
            );
        });

        return to_route('accounts.edit')->with('success', __('accounts.flash.updated'));
    }

    public function toggleActive(Request $request, Account $account): RedirectResponse
    {
        $account = $this->ownedAccount($request, $account);
        $account->loadMissing('accountType');

        if ($account->isProtectedCashAccount()) {
            throw ValidationException::withMessages([
                'toggle' => __('accounts.validation.protected_cash_account_active_locked'),
            ]);
        }

        $account->forceFill([
            'is_active' => ! $account->is_active,
        ])->save();

        return to_route('accounts.edit')->with(
            'success',
            $account->is_active
                ? __('accounts.flash.activated')
                : __('accounts.flash.deactivated')
        );
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $account = $this->ownedAccount($request, $account);
        $account->loadMissing('accountType');

        if ($account->isProtectedCashAccount()) {
            throw ValidationException::withMessages([
                'delete' => __('accounts.validation.protected_cash_account_delete_locked'),
            ]);
        }

        $blockingReasons = $this->blockingReasons($account);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => 'Questo conto non può essere eliminato: '
                .implode(', ', $blockingReasons)
                .'. '.__('accounts.validation.delete_suffix'),
            ]);
        }

        $account->delete();

        return to_route('accounts.edit')->with('success', __('accounts.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(User $user): array
    {
        $balanceConstraintService = app(AccountBalanceConstraintService::class);
        $userId = $user->id;

        $accounts = Account::query()
            ->ownedBy($userId)
            ->with([
                'bank:id,uuid,name,country_code',
                'userBank.bank:id,uuid,name,slug,country_code',
                'accountType:id,uuid,code,name,balance_nature',
            ])
            ->withCount([
                'transactions',
                'imports',
                'openingBalances',
                'balanceSnapshots',
                'reconciliations',
                'recurringEntries',
                'scheduledEntries',
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get([
                'uuid',
                'bank_id',
                'user_bank_id',
                'account_type_id',
                'name',
                'iban',
                'account_number_masked',
                'currency',
                'currency_code',
                'opening_balance',
                'opening_balance_date',
                'current_balance',
                'is_manual',
                'is_active',
                'is_reported',
                'notes',
                'settings',
            ]);

        $linkedByCreditCardCounts = $accounts->reduce(
            function (array $counts, Account $account): array {
                if ($account->accountType?->code !== AccountTypeCodeEnum::CREDIT_CARD->value) {
                    return $counts;
                }

                $linkedId = data_get($account->settings, 'linked_payment_account_id');

                if (! is_int($linkedId)) {
                    return $counts;
                }

                $counts[$linkedId] = ($counts[$linkedId] ?? 0) + 1;

                return $counts;
            },
            []
        );

        $linkedPaymentAccountIds = $accounts
            ->pluck('settings')
            ->filter(fn ($settings) => is_array($settings))
            ->map(fn (array $settings) => $settings['linked_payment_account_id'] ?? null)
            ->filter(fn ($value) => is_int($value))
            ->unique()
            ->values();

        $linkedPaymentAccounts = Account::query()
            ->ownedBy($userId)
            ->with(['bank:id,uuid,name', 'accountType:id,uuid,code,name,balance_nature'])
            ->whereIn('id', $linkedPaymentAccountIds)
            ->get(['id', 'uuid', 'bank_id', 'user_bank_id', 'account_type_id', 'name', 'currency', 'currency_code', 'is_active'])
            ->keyBy('id');

        $accountItems = $accounts->map(function (Account $account) use ($linkedByCreditCardCounts, $linkedPaymentAccounts, $balanceConstraintService): array {
            $settings = is_array($account->settings) ? $account->settings : [];
            $balanceNature = $account->accountType?->balance_nature;
            $linkedPaymentAccount = $linkedPaymentAccounts->get(
                data_get($settings, 'linked_payment_account_id')
            );
            $linkedCreditCardsCount = $linkedByCreditCardCounts[$account->id] ?? 0;

            $counts = [
                'transactions' => (int) $account->transactions_count,
                'imports' => (int) $account->imports_count,
                'opening_balances' => (int) $account->opening_balances_count,
                'balance_snapshots' => (int) $account->balance_snapshots_count,
                'reconciliations' => (int) $account->reconciliations_count,
                'recurring_entries' => (int) $account->recurring_entries_count,
                'scheduled_entries' => (int) $account->scheduled_entries_count,
                'linked_credit_cards' => $linkedCreditCardsCount,
            ];

            $usageCount = array_sum($counts);
            $canUpdateCurrency = $usageCount === 0;
            $userBank = $account->userBank;
            $displayBankName = $userBank?->name ?? $account->bank?->name;
            $accountTypeCode = AccountTypeCodeEnum::from($account->accountType->code);

            return [
                'uuid' => $account->uuid,
                'bank_uuid' => $userBank?->uuid ?? $account->bank?->uuid,
                'user_bank_uuid' => $userBank?->uuid,
                'account_type_uuid' => $account->accountType->uuid,
                'name' => $account->name,
                'iban' => $account->iban,
                'account_number_masked' => $account->account_number_masked,
                'currency' => $account->currency_code ?: $account->currency,
                'currency_label' => $this->currencyOptionLabel($account->currency_code ?: $account->currency),
                'opening_balance' => $account->opening_balance !== null ? (float) $account->opening_balance : null,
                'opening_balance_direction' => (float) ($account->opening_balance ?? 0) < 0 ? 'negative' : 'positive',
                'opening_balance_date' => $account->opening_balance_date?->toDateString(),
                'current_balance' => $account->current_balance !== null ? (float) $account->current_balance : null,
                'is_manual' => (bool) $account->is_manual,
                'is_active' => (bool) $account->is_active,
                'is_reported' => (bool) $account->is_reported,
                'is_default' => (bool) $account->is_default,
                'notes' => $account->notes,
                'settings' => $settings !== [] ? $settings : null,
                'bank' => $userBank === null ? null : [
                    'uuid' => $userBank->uuid,
                    'bank_uuid' => $userBank->bank?->uuid,
                    'name' => $userBank->name,
                    'slug' => $userBank->slug,
                    'is_custom' => (bool) $userBank->is_custom,
                    'is_active' => (bool) $userBank->is_active,
                    'source_label' => $userBank->is_custom ? __('settings.banks.source.custom') : __('settings.banks.source.catalog'),
                    'country_code' => $userBank->bank?->country_code,
                    'catalog_name' => $userBank->bank?->name,
                ],
                'bank_name' => $displayBankName,
                'account_type' => [
                    'uuid' => $account->accountType->uuid,
                    'code' => $account->accountType->code,
                    'name' => $accountTypeCode->label(),
                    'balance_nature' => $balanceNature?->value,
                    'balance_nature_label' => $balanceNature?->label(),
                ],
                'balance_nature' => $balanceNature?->value,
                'balance_nature_label' => $balanceNature?->label(),
                'linked_payment_account' => $linkedPaymentAccount === null
                    ? null
                    : $this->mapLinkedPaymentAccountOption($linkedPaymentAccount),
                'credit_card_settings' => $account->accountType->code === AccountTypeCodeEnum::CREDIT_CARD->value
                    ? [
                        'credit_limit' => data_get($settings, 'credit_limit') !== null
                            ? (float) data_get($settings, 'credit_limit')
                            : null,
                        'linked_payment_account_uuid' => $linkedPaymentAccount?->uuid,
                        'statement_closing_day' => data_get($settings, 'statement_closing_day'),
                        'payment_day' => data_get($settings, 'payment_day'),
                        'auto_pay' => (bool) data_get($settings, 'auto_pay', false),
                    ]
                    : null,
                'counts' => $counts,
                'usage_count' => $usageCount,
                'used' => $usageCount > 0,
                'is_deletable' => $usageCount === 0 && ! $account->isProtectedCashAccount(),
                'can_update_currency' => $canUpdateCurrency,
                'currency_lock_message' => $canUpdateCurrency
                    ? null
                    : __('accounts.validation.currency_locked_after_usage'),
                'can_toggle_active' => ! $account->isProtectedCashAccount(),
                'is_protected_cash_account' => $account->isProtectedCashAccount(),
                'allow_negative_balance' => $balanceConstraintService->allowsNegativeBalance(
                    $account->accountType,
                    $settings,
                ),
            ];
        })->values()->all();

        return [
            'accounts' => [
                'data' => $accountItems,
                'summary' => [
                    'total_count' => count($accountItems),
                    'active_count' => collect($accountItems)->where('is_active', true)->count(),
                    'inactive_count' => collect($accountItems)->where('is_active', false)->count(),
                    'manual_count' => collect($accountItems)->where('is_manual', true)->count(),
                    'credit_cards_count' => collect($accountItems)->where('account_type.code', AccountTypeCodeEnum::CREDIT_CARD->value)->count(),
                    'used_count' => collect($accountItems)->where('used', true)->count(),
                ],
            ],
            'shared_accounts' => $this->accessibleAccountsQuery
                ->get($user, 'shared')
                ->loadMissing('user:id,name')
                ->map(function (Account $account): array {
                    $membershipRole = AccountMembershipRoleEnum::tryFrom(
                        (string) $account->getAttribute('membership_role')
                    );
                    $membershipStatus = AccountMembershipStatusEnum::tryFrom(
                        (string) $account->getAttribute('membership_status')
                    );

                    return [
                        'uuid' => $account->uuid,
                        'membership_uuid' => $account->getAttribute('membership_uuid'),
                        'name' => $account->name,
                        'bank_name' => $account->userBank?->name ?? $account->bank?->name,
                        'currency' => $account->currency_code ?: $account->currency,
                        'current_balance' => $account->current_balance !== null ? (float) $account->current_balance : null,
                        'is_active' => (bool) $account->is_active,
                        'owner_name' => $account->user?->name,
                        'membership_role' => $membershipRole?->value,
                        'membership_role_label' => $membershipRole?->label(),
                        'membership_status' => $membershipStatus?->value,
                        'membership_status_label' => $membershipStatus?->label(),
                        'can_leave' => $membershipStatus === AccountMembershipStatusEnum::ACTIVE
                            && is_string($account->getAttribute('membership_uuid'))
                            && $account->getAttribute('membership_uuid') !== '',
                    ];
                })
                ->values()
                ->all(),
            'options' => [
                'opening_balance_date' => $this->openingBalanceDateOptions($user),
                'banks' => UserBank::query()
                    ->ownedBy($userId)
                    ->where('is_active', true)
                    ->with('bank:id,uuid,name,country_code,logo_url')
                    ->orderByDesc('is_custom')
                    ->orderBy('name')
                    ->get(['uuid', 'bank_id', 'name', 'slug', 'is_custom', 'is_active'])
                    ->map(fn (UserBank $userBank): array => [
                        'uuid' => $userBank->uuid,
                        'bank_uuid' => $userBank->bank?->uuid,
                        'name' => $userBank->name,
                        'slug' => $userBank->slug,
                        'is_custom' => (bool) $userBank->is_custom,
                        'is_active' => (bool) $userBank->is_active,
                        'source_label' => $userBank->is_custom ? __('settings.banks.source.custom') : __('settings.banks.source.catalog'),
                        'country_code' => $userBank->bank?->country_code,
                        'catalog_name' => $userBank->bank?->name,
                        'logo_url' => $userBank->bank?->logo_url,
                    ])
                    ->values()
                    ->all(),
                'account_types' => AccountType::query()
                    ->orderBy('id')
                    ->get(['uuid', 'code', 'name', 'balance_nature'])
                    ->map(fn (AccountType $accountType): array => [
                        'uuid' => $accountType->uuid,
                        'code' => $accountType->code,
                        'name' => AccountTypeCodeEnum::from($accountType->code)->label(),
                        'balance_nature' => $accountType->balance_nature?->value,
                        'balance_nature_label' => $accountType->balance_nature?->label(),
                        'default_allow_negative_balance' => $balanceConstraintService->defaultAllowNegativeBalance($accountType),
                    ])
                    ->values()
                    ->all(),
                'balance_natures' => collect(AccountBalanceNatureEnum::cases())
                    ->map(fn (AccountBalanceNatureEnum $nature): array => [
                        'value' => $nature->value,
                        'label' => $nature->label(),
                    ])
                    ->values()
                    ->all(),
                'currencies' => collect($this->currencySupport->options())
                    ->map(fn (array $currency): array => [
                        'code' => $currency['code'],
                        'name' => $currency['name'],
                        'symbol' => $currency['symbol'],
                        'minor_unit' => $currency['minor_unit'],
                        'symbol_position' => $currency['symbol_position'],
                        'label' => sprintf(
                            '%s — %s (%s)',
                            $currency['code'],
                            $currency['name'],
                            $currency['symbol']
                        ),
                    ])
                    ->values()
                    ->all(),
                'linked_payment_accounts' => Account::query()
                    ->ownedBy($userId)
                    ->with(['userBank.bank:id,uuid,name', 'bank:id,uuid,name', 'accountType:id,uuid,code,name,balance_nature'])
                    ->whereHas('accountType', fn ($query) => $query
                        ->whereNotIn('code', [
                            AccountTypeCodeEnum::CREDIT_CARD->value,
                            AccountTypeCodeEnum::CASH_ACCOUNT->value,
                        ]))
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get(['id', 'uuid', 'bank_id', 'user_bank_id', 'account_type_id', 'name', 'currency', 'currency_code', 'is_active'])
                    ->map(fn (Account $account): array => $this->mapLinkedPaymentAccountOption($account))
                    ->values()
                    ->all(),
                'default_account_uuid' => Account::query()
                    ->defaultOwnedBy($userId)
                    ->value('uuid'),
            ],
        ];
    }

    protected function syncDefaultAccountForUser(User $user, Account $account): void
    {
        if (! $account->is_default || ! $account->is_active) {
            if ($account->is_default && ! $account->is_active) {
                $account->forceFill(['is_default' => false])->save();
            }

            return;
        }

        Account::query()
            ->ownedBy($user->id)
            ->whereKeyNot($account->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    protected function shouldAutoAssignDefaultAccount(User $user, Account $account): bool
    {
        if (! $account->is_active || $account->user_bank_id === null) {
            return false;
        }

        return ! Account::query()
            ->defaultOwnedBy($user->id)
            ->exists();
    }

    protected function ownedAccount(Request $request, Account $account): Account
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        return $account;
    }

    /**
     * @return array<int, string>
     */
    protected function blockingReasons(Account $account): array
    {
        $account->loadCount([
            'transactions',
            'imports',
            'openingBalances',
            'balanceSnapshots',
            'reconciliations',
            'recurringEntries',
            'scheduledEntries',
        ]);

        $linkedCreditCardsCount = Account::query()
            ->ownedBy($account->user_id)
            ->whereHas('accountType', fn ($query) => $query->where('code', AccountTypeCodeEnum::CREDIT_CARD->value))
            ->get(['id', 'settings'])
            ->filter(fn (Account $creditCard) => data_get($creditCard->settings, 'linked_payment_account_id') === $account->id)
            ->count();

        $reasons = [];

        $labels = [
            'transactions_count' => 'transazioni',
            'imports_count' => 'import',
            'opening_balances_count' => 'saldi iniziali registrati',
            'balance_snapshots_count' => 'snapshot di saldo',
            'reconciliations_count' => 'riconciliazioni',
            'recurring_entries_count' => 'ricorrenze',
            'scheduled_entries_count' => 'scadenze pianificate',
        ];

        foreach ($labels as $countKey => $label) {
            $count = (int) $account->{$countKey};

            if ($count > 0) {
                $reasons[] = $count === 1
                    ? "è usato in 1 {$label}"
                    : "è usato in {$count} {$label}";
            }
        }

        if ($linkedCreditCardsCount > 0) {
            $reasons[] = $linkedCreditCardsCount === 1
                ? 'è collegato come conto di addebito a 1 carta di credito'
                : "è collegato come conto di addebito a {$linkedCreditCardsCount} carte di credito";
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapLinkedPaymentAccountOption(Account $account): array
    {
        return [
            'currency' => $account->currency_code ?: $account->currency,
            'uuid' => $account->uuid,
            'name' => $account->name,
            'bank_uuid' => $account->userBank?->bank?->uuid ?? $account->bank?->uuid,
            'user_bank_uuid' => $account->userBank?->uuid,
            'bank_name' => $account->userBank?->name ?? $account->bank?->name,
            'account_type_name' => $account->accountType?->code
                ? AccountTypeCodeEnum::from($account->accountType->code)->label()
                : $account->name,
            'account_type_code' => $account->accountType?->code ?? '',
            'balance_nature' => $account->accountType?->balance_nature?->value,
            'is_active' => (bool) $account->is_active,
            'label' => collect([
                $account->name,
                $account->userBank?->name ?? $account->bank?->name,
                $account->currency_code ?: $account->currency,
            ])->filter()->implode(' • '),
        ];
    }

    /**
     * @return array<string, array<int, int>|string|null>
     */
    protected function openingBalanceDateOptions(User $user): array
    {
        $availableYears = $user->years()
            ->where('is_closed', false)
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->values()
            ->all();

        $today = now()->toDateString();
        $minDate = $availableYears === []
            ? null
            : sprintf('%d-01-01', min($availableYears));
        $maxAvailableYear = $availableYears === [] ? null : max($availableYears);
        $maxDate = $maxAvailableYear === null
            ? $today
            : min($today, sprintf('%d-12-31', $maxAvailableYear));

        return [
            'available_years' => $availableYears,
            'min' => $minDate,
            'max' => $maxDate,
            'today' => $today,
        ];
    }

    protected function currencyOptionLabel(string $currencyCode): string
    {
        $currency = $this->currencySupport->for($currencyCode);

        if (! is_array($currency)) {
            return $currencyCode;
        }

        return sprintf(
            '%s — %s (%s)',
            $currency['code'],
            $currency['name'],
            $currency['symbol']
        );
    }
}
