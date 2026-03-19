<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountTypeCodeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAccountRequest;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Scope;
use App\Models\UserBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Accounts', $payload);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userBank = $request->resolveRequestedUserBank();

        Account::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'user_bank_id' => $userBank?->id,
            'bank_id' => $userBank?->bank_id,
            'settings' => $request->normalizedSettings(),
        ]);

        return to_route('accounts.edit')->with('success', 'Account creato correttamente.');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $account = $this->ownedAccount($request, $account);
        $validated = $request->validated();
        $userBank = $request->resolveRequestedUserBank();

        $account->fill([
            ...$validated,
            'user_bank_id' => $userBank?->id,
            'bank_id' => $userBank?->bank_id,
            'settings' => $request->normalizedSettings($account->settings),
        ]);
        $account->save();

        return to_route('accounts.edit')->with('success', 'Account aggiornato correttamente.');
    }

    public function toggleActive(Request $request, Account $account): RedirectResponse
    {
        $account = $this->ownedAccount($request, $account);

        $account->forceFill([
            'is_active' => ! $account->is_active,
        ])->save();

        return to_route('accounts.edit')->with(
            'success',
            $account->is_active
                ? 'Account attivato correttamente.'
                : 'Account disattivato correttamente.'
        );
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $account = $this->ownedAccount($request, $account);

        $blockingReasons = $this->blockingReasons($account);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => 'Questo account non può essere eliminato: '
                    .implode(', ', $blockingReasons)
                    .'. Disattivalo invece per conservarne lo storico.',
            ]);
        }

        $account->delete();

        return to_route('accounts.edit')->with('success', 'Account eliminato correttamente.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(int $userId): array
    {
        $accounts = Account::query()
            ->ownedBy($userId)
            ->with([
                'bank:id,name,country_code',
                'userBank.bank:id,name,slug,country_code',
                'accountType:id,code,name,balance_nature',
                'scope:id,user_id,name,type,color,is_active',
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
                'id',
                'user_id',
                'bank_id',
                'user_bank_id',
                'account_type_id',
                'scope_id',
                'name',
                'iban',
                'account_number_masked',
                'currency',
                'opening_balance',
                'current_balance',
                'is_manual',
                'is_active',
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
            ->with(['bank:id,name', 'accountType:id,code,name,balance_nature'])
            ->whereIn('id', $linkedPaymentAccountIds)
            ->get(['id', 'bank_id', 'user_bank_id', 'account_type_id', 'name', 'currency', 'is_active'])
            ->keyBy('id');

        $accountItems = $accounts->map(function (Account $account) use ($linkedByCreditCardCounts, $linkedPaymentAccounts): array {
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
            $userBank = $account->userBank;
            $displayBankName = $userBank?->name ?? $account->bank?->name;

            return [
                'id' => $account->id,
                'bank_id' => $account->bank_id,
                'user_bank_id' => $account->user_bank_id,
                'account_type_id' => $account->account_type_id,
                'scope_id' => $account->scope_id,
                'name' => $account->name,
                'iban' => $account->iban,
                'account_number_masked' => $account->account_number_masked,
                'currency' => $account->currency,
                'opening_balance' => $account->opening_balance !== null ? (float) $account->opening_balance : null,
                'current_balance' => $account->current_balance !== null ? (float) $account->current_balance : null,
                'is_manual' => (bool) $account->is_manual,
                'is_active' => (bool) $account->is_active,
                'notes' => $account->notes,
                'settings' => $settings !== [] ? $settings : null,
                'bank' => $userBank === null ? null : [
                    'id' => $userBank->id,
                    'bank_id' => $userBank->bank_id,
                    'name' => $userBank->name,
                    'slug' => $userBank->slug,
                    'is_custom' => (bool) $userBank->is_custom,
                    'is_active' => (bool) $userBank->is_active,
                    'source_label' => $userBank->is_custom ? 'Personalizzata' : 'Globale',
                    'country_code' => $userBank->bank?->country_code,
                    'catalog_name' => $userBank->bank?->name,
                ],
                'bank_name' => $displayBankName,
                'scope' => $account->scope === null ? null : [
                    'id' => $account->scope->id,
                    'name' => $account->scope->name,
                    'type' => $account->scope->type,
                    'color' => $account->scope->color,
                    'is_active' => (bool) $account->scope->is_active,
                ],
                'account_type' => [
                    'id' => $account->accountType->id,
                    'code' => $account->accountType->code,
                    'name' => $account->accountType->name,
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
                        'linked_payment_account_id' => data_get($settings, 'linked_payment_account_id'),
                        'statement_closing_day' => data_get($settings, 'statement_closing_day'),
                        'payment_day' => data_get($settings, 'payment_day'),
                        'auto_pay' => (bool) data_get($settings, 'auto_pay', false),
                    ]
                    : null,
                'counts' => $counts,
                'usage_count' => $usageCount,
                'used' => $usageCount > 0,
                'is_deletable' => $usageCount === 0,
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
            'options' => [
                'banks' => UserBank::query()
                    ->ownedBy($userId)
                    ->where('is_active', true)
                    ->with('bank:id,name,country_code')
                    ->orderByDesc('is_custom')
                    ->orderBy('name')
                    ->get(['id', 'bank_id', 'name', 'slug', 'is_custom', 'is_active'])
                    ->map(fn (UserBank $userBank): array => [
                        'id' => $userBank->id,
                        'bank_id' => $userBank->bank_id,
                        'name' => $userBank->name,
                        'slug' => $userBank->slug,
                        'is_custom' => (bool) $userBank->is_custom,
                        'is_active' => (bool) $userBank->is_active,
                        'source_label' => $userBank->is_custom ? 'Personalizzata' : 'Globale',
                        'country_code' => $userBank->bank?->country_code,
                        'catalog_name' => $userBank->bank?->name,
                    ])
                    ->values()
                    ->all(),
                'account_types' => AccountType::query()
                    ->orderBy('id')
                    ->get(['id', 'code', 'name', 'balance_nature'])
                    ->map(fn (AccountType $accountType): array => [
                        'id' => $accountType->id,
                        'code' => $accountType->code,
                        'name' => $accountType->name,
                        'balance_nature' => $accountType->balance_nature?->value,
                        'balance_nature_label' => $accountType->balance_nature?->label(),
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
                'scopes' => Scope::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get(['id', 'name', 'type', 'color', 'is_active'])
                    ->map(fn (Scope $scope): array => [
                        'id' => $scope->id,
                        'name' => $scope->name,
                        'type' => $scope->type,
                        'color' => $scope->color,
                        'is_active' => (bool) $scope->is_active,
                    ])
                    ->values()
                    ->all(),
                'linked_payment_accounts' => Account::query()
                    ->ownedBy($userId)
                    ->with(['userBank.bank:id,name', 'bank:id,name', 'accountType:id,code,name,balance_nature'])
                    ->whereHas('accountType', fn ($query) => $query->where('code', '!=', AccountTypeCodeEnum::CREDIT_CARD->value))
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get(['id', 'bank_id', 'user_bank_id', 'account_type_id', 'name', 'currency', 'is_active'])
                    ->map(fn (Account $account): array => $this->mapLinkedPaymentAccountOption($account))
                    ->values()
                    ->all(),
            ],
        ];
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
            'id' => $account->id,
            'name' => $account->name,
            'bank_name' => $account->userBank?->name ?? $account->bank?->name,
            'currency' => $account->currency,
            'account_type_name' => $account->accountType?->name ?? $account->name,
            'account_type_code' => $account->accountType?->code ?? '',
            'balance_nature' => $account->accountType?->balance_nature?->value,
            'is_active' => (bool) $account->is_active,
            'label' => collect([
                $account->name,
                $account->userBank?->name ?? $account->bank?->name,
                $account->currency,
            ])->filter()->implode(' • '),
        ];
    }
}
