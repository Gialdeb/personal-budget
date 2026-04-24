<?php

namespace App\Services\Reports;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalLedgerAnalyticsService;
use App\Services\UserYearService;
use App\Support\Banks\BankNamePresenter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use NumberFormatter;

class AccountReportExportService
{
    protected const PERIOD_ANNUAL = 'annual';

    protected const PERIOD_MONTHLY = 'monthly';

    protected const PERIOD_LAST_THREE_MONTHS = 'last_3_months';

    protected const PERIOD_LAST_SIX_MONTHS = 'last_6_months';

    protected const PERIOD_YTD = 'ytd';

    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected UserYearService $userYearService,
        protected OperationalLedgerAnalyticsService $operationalLedgerAnalyticsService,
    ) {}

    /**
     * @param  array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null}  $input
     * @return array<string, mixed>
     */
    public function build(User $user, int $defaultYear, ?int $defaultMonth, array $input = []): array
    {
        $previousLocale = App::currentLocale();
        $locale = $user->preferredLocale();
        App::setLocale($locale);

        try {
            return $this->buildLocalized($user, $defaultYear, $defaultMonth, $input, $locale);
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * @param  array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null}  $input
     * @return array<string, mixed>
     */
    protected function buildLocalized(User $user, int $defaultYear, ?int $defaultMonth, array $input, string $locale): array
    {
        $availableYears = $this->availableYears($user, $defaultYear);
        $selectedYear = $this->normalizeYear($input['year'] ?? null, $availableYears, $defaultYear);
        $selectedPeriod = $this->normalizePeriod($input['period'] ?? null);
        $referenceMonth = $this->normalizeReferenceMonth($input['month'] ?? null, $defaultMonth, $selectedYear);
        $accounts = $this->accessibleAccountsQuery->get($user)->values();
        $selectedAccountUuid = $this->normalizeAccountUuid($accounts, $input['account_uuid'] ?? null);
        $currency = strtoupper(trim((string) ($user->base_currency_code ?: 'EUR')));
        $periodDefinition = $this->buildPeriodDefinition($selectedYear, $selectedPeriod, $referenceMonth);
        $selectedAccounts = $selectedAccountUuid === null
            ? $accounts
            : $accounts->filter(fn (Account $account): bool => (string) $account->uuid === $selectedAccountUuid)->values();
        $ledgerTransactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
            $user,
            $periodDefinition['start'],
            $periodDefinition['end'],
            $selectedAccountUuid,
        );
        $transactions = $this->hydrateTransactions($ledgerTransactions);
        $totals = $this->operationalLedgerAnalyticsService->summarize($ledgerTransactions, $currency);
        $groups = $this->accountGroups($selectedAccounts, $transactions, $periodDefinition, $currency);
        $openingBalanceRaw = round((float) collect($groups)->sum('opening_balance_raw'), 2);
        $closingBalanceRaw = round((float) collect($groups)->sum('closing_balance_raw'), 2);
        $generatedAt = now(config('app.timezone'));

        return [
            'locale' => $locale,
            'generated_at' => $this->formatDateTime($generatedAt, $locale),
            'currency' => $currency,
            'filename' => $this->filename($selectedAccountUuid === null ? null : $selectedAccounts->first(), $periodDefinition, $locale, $generatedAt),
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedPeriod === self::PERIOD_ANNUAL ? null : $referenceMonth,
                'period' => $selectedPeriod,
                'account_uuid' => $selectedAccountUuid,
                'start_date' => $periodDefinition['start']->toDateString(),
                'end_date' => $periodDefinition['end']->toDateString(),
            ],
            'meta' => [
                'title' => $this->label($locale, 'title'),
                'scope_label' => $selectedAccountUuid === null
                    ? $this->label($locale, 'all_accounts')
                    : ($selectedAccounts->first()?->name ?? $this->label($locale, 'all_accounts')),
                'period_label' => $this->periodLabel($periodDefinition),
                'period_range' => $this->formatDate($periodDefinition['start'], $locale).' - '.$this->formatDate($periodDefinition['end'], $locale),
                'opening_balance' => $this->formatMoney($openingBalanceRaw, $currency),
                'opening_balance_raw' => $openingBalanceRaw,
                'closing_balance' => $this->formatMoney($closingBalanceRaw, $currency),
                'closing_balance_raw' => $closingBalanceRaw,
                'net_change' => $this->formatMoney($totals['net'], $currency),
                'net_change_raw' => $totals['net'],
                'income' => $this->formatMoney($totals['income'], $currency),
                'expense' => $this->formatMoney($totals['expense'], $currency),
                'transactions_count' => $transactions->count(),
            ],
            'groups' => $groups,
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $ledgerTransactions
     * @return Collection<int, Transaction>
     */
    protected function hydrateTransactions(Collection $ledgerTransactions): Collection
    {
        $uuids = $ledgerTransactions->pluck('uuid')->filter()->values()->all();

        if ($uuids === []) {
            return collect();
        }

        return Transaction::query()
            ->with(['account.bank', 'merchant'])
            ->whereIn('uuid', $uuids)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array<string, mixed>>
     */
    protected function accountGroups(Collection $accounts, Collection $transactions, array $periodDefinition, string $currency): array
    {
        return $accounts
            ->map(function (Account $account) use ($transactions, $periodDefinition, $currency): array {
                $openingBalance = $this->balanceAt($account, $periodDefinition['start']->subDay());
                $runningBalance = $openingBalance;
                $accountTransactions = $transactions
                    ->where('account_id', $account->id)
                    ->values()
                    ->map(function (Transaction $transaction) use (&$runningBalance, $currency): array {
                        $balanceAmount = $this->signedTransactionAmount($transaction);
                        $displayAmount = $this->signedDisplayAmount($transaction, $currency);
                        $runningBalance = $transaction->balance_after !== null
                            ? round((float) $transaction->balance_after, 2)
                            : round($runningBalance + $balanceAmount, 2);

                        return [
                            'date' => $this->formatDate(CarbonImmutable::parse($transaction->transaction_date), app()->getLocale()),
                            'title' => $transaction->description ?: $this->label(app()->getLocale(), 'movement'),
                            'note' => $this->transactionNote($transaction),
                            'amount' => $this->formatMoney($displayAmount, $currency),
                            'amount_raw' => $displayAmount,
                            'balance' => $this->formatMoney($runningBalance, $currency),
                            'balance_raw' => $runningBalance,
                        ];
                    });
                $closingBalance = $this->balanceAt($account, $periodDefinition['end']);

                return [
                    'account_uuid' => (string) $account->uuid,
                    'account_name' => $account->name,
                    'bank_name' => BankNamePresenter::forAccount($account),
                    'opening_balance' => $this->formatMoney($openingBalance, $currency),
                    'opening_balance_raw' => $openingBalance,
                    'closing_balance' => $this->formatMoney((float) $closingBalance, $currency),
                    'closing_balance_raw' => round((float) $closingBalance, 2),
                    'net_change' => $this->formatMoney(round((float) $accountTransactions->sum('amount_raw'), 2), $currency),
                    'transactions' => $accountTransactions->all(),
                ];
            })
            ->filter(fn (array $group): bool => $group['transactions'] !== [] || abs((float) $group['opening_balance_raw']) > 0.005 || abs((float) $group['closing_balance_raw']) > 0.005)
            ->values()
            ->all();
    }

    protected function transactionNote(Transaction $transaction): string
    {
        return collect([
            $transaction->merchant?->name,
            $transaction->counterparty_name,
            $transaction->reference_code,
            $transaction->notes,
            $transaction->bank_description_clean,
        ])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->join(' - ') ?: '-';
    }

    protected function balanceAt(Account $account, CarbonImmutable $date): float
    {
        $lastBalance = Transaction::query()
            ->where('account_id', $account->id)
            ->whereNotNull('balance_after')
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->value('balance_after');

        if ($lastBalance !== null) {
            return round((float) $lastBalance, 2);
        }

        $balance = (float) ($account->opening_balance ?? 0.0);
        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->get(['direction', 'amount']);

        foreach ($transactions as $transaction) {
            $balance += $this->signedTransactionAmount($transaction);
        }

        return round($balance, 2);
    }

    protected function signedTransactionAmount(Transaction $transaction): float
    {
        $amount = abs((float) $transaction->amount);

        return $transaction->direction === TransactionDirectionEnum::EXPENSE
            ? round($amount * -1, 2)
            : round($amount, 2);
    }

    protected function signedDisplayAmount(Transaction $transaction, string $currency): float
    {
        $amount = $this->operationalLedgerAnalyticsService->resolveAggregateAmountForTransaction($transaction, $currency)
            ?? abs((float) $transaction->amount);

        return $transaction->direction === TransactionDirectionEnum::EXPENSE
            ? round($amount * -1, 2)
            : round($amount, 2);
    }

    protected function normalizeAccountUuid(Collection $accounts, ?string $requestedUuid): ?string
    {
        if ($requestedUuid === null || $requestedUuid === '' || $requestedUuid === 'all') {
            return null;
        }

        $selected = $accounts->firstWhere('uuid', $requestedUuid);

        return $selected instanceof Account ? (string) $selected->uuid : null;
    }

    /**
     * @param  array<int, int>  $availableYears
     */
    protected function normalizeYear(?int $requestedYear, array $availableYears, int $fallbackYear): int
    {
        if ($requestedYear !== null && in_array($requestedYear, $availableYears, true)) {
            return $requestedYear;
        }

        return in_array($fallbackYear, $availableYears, true) ? $fallbackYear : max($availableYears);
    }

    protected function normalizePeriod(?string $requestedPeriod): string
    {
        return in_array($requestedPeriod, [
            self::PERIOD_ANNUAL,
            self::PERIOD_MONTHLY,
            self::PERIOD_LAST_THREE_MONTHS,
            self::PERIOD_LAST_SIX_MONTHS,
            self::PERIOD_YTD,
        ], true) ? $requestedPeriod : self::PERIOD_ANNUAL;
    }

    protected function normalizeReferenceMonth(?int $requestedMonth, ?int $defaultMonth, int $selectedYear): int
    {
        if ($requestedMonth !== null && $requestedMonth >= 1 && $requestedMonth <= 12) {
            return $requestedMonth;
        }

        if ($defaultMonth !== null && $defaultMonth >= 1 && $defaultMonth <= 12) {
            return $defaultMonth;
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        return $selectedYear === $now->year ? $now->month : 12;
    }

    /**
     * @return array<int, int>
     */
    protected function availableYears(User $user, int $defaultYear): array
    {
        $years = $this->userYearService->availableYears($user);

        return $years === [] ? [$defaultYear] : $years;
    }

    /**
     * @return array{period: string, start: CarbonImmutable, end: CarbonImmutable, granularity: 'day'|'month', reference_month: int}
     */
    protected function buildPeriodDefinition(int $year, string $period, int $referenceMonth): array
    {
        $referenceDate = CarbonImmutable::create($year, $referenceMonth, 1, 0, 0, 0, config('app.timezone'));

        return match ($period) {
            self::PERIOD_ANNUAL => [
                'period' => $period,
                'start' => $referenceDate->startOfYear(),
                'end' => $referenceDate->endOfYear(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            self::PERIOD_LAST_THREE_MONTHS => [
                'period' => $period,
                'start' => $referenceDate->startOfMonth()->subMonths(2),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            self::PERIOD_LAST_SIX_MONTHS => [
                'period' => $period,
                'start' => $referenceDate->startOfMonth()->subMonths(5),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            self::PERIOD_YTD => [
                'period' => $period,
                'start' => $referenceDate->startOfYear(),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            default => [
                'period' => self::PERIOD_MONTHLY,
                'start' => $referenceDate->startOfMonth(),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'day',
                'reference_month' => $referenceMonth,
            ],
        };
    }

    protected function periodLabel(array $periodDefinition): string
    {
        return match ($periodDefinition['period']) {
            self::PERIOD_ANNUAL => (string) $periodDefinition['start']->year,
            self::PERIOD_MONTHLY => $periodDefinition['start']->translatedFormat('F Y'),
            self::PERIOD_LAST_THREE_MONTHS => $this->label(app()->getLocale(), 'last_3_months', [
                'month_year' => $periodDefinition['end']->translatedFormat('F Y'),
            ]),
            self::PERIOD_LAST_SIX_MONTHS => $this->label(app()->getLocale(), 'last_6_months', [
                'month_year' => $periodDefinition['end']->translatedFormat('F Y'),
            ]),
            self::PERIOD_YTD => $this->label(app()->getLocale(), 'ytd', [
                'month_year' => $periodDefinition['end']->translatedFormat('F Y'),
            ]),
            default => (string) $periodDefinition['start']->year,
        };
    }

    protected function formatMoney(float $amount, string $currency): string
    {
        $formatter = new NumberFormatter($this->formatLocale(app()->getLocale()), NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 2);
        $formatted = $formatter->format(abs($amount));

        $number = is_string($formatted) ? $formatted : number_format(abs($amount), 2, '.', ',');
        $sign = $amount < 0 ? '-' : '';

        return sprintf('%s %s%s', $currency, $sign, $number);
    }

    protected function formatDate(CarbonInterface $date, string $locale): string
    {
        return str_starts_with($locale, 'en')
            ? $date->format('m/d/Y')
            : $date->format('d/m/Y');
    }

    protected function formatDateTime(CarbonInterface $date, string $locale): string
    {
        return str_starts_with($locale, 'en')
            ? $date->format('m/d/Y H:i')
            : $date->format('d/m/Y H:i');
    }

    protected function formatLocale(string $locale): string
    {
        return match ($locale) {
            'en' => 'en_US',
            'it' => 'it_IT',
            default => str_replace('-', '_', $locale),
        };
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected function label(string $locale, string $key, array $replace = []): string
    {
        $labels = [
            'it' => [
                'title' => 'Estratto conto',
                'all_accounts' => 'Tutti i conti',
                'movement' => 'Movimento',
                'last_3_months' => 'Ultimi 3 mesi fino a :month_year',
                'last_6_months' => 'Ultimi 6 mesi fino a :month_year',
                'ytd' => 'Da inizio anno fino a :month_year',
            ],
            'en' => [
                'title' => 'Account statement',
                'all_accounts' => 'All accounts',
                'movement' => 'Movement',
                'last_3_months' => 'Last 3 months through :month_year',
                'last_6_months' => 'Last 6 months through :month_year',
                'ytd' => 'Year to date through :month_year',
            ],
        ];

        $line = $labels[str_starts_with($locale, 'en') ? 'en' : 'it'][$key] ?? $key;

        foreach ($replace as $replaceKey => $value) {
            $line = str_replace(':'.$replaceKey, $value, $line);
        }

        return $line;
    }

    protected function filename(?Account $account, array $periodDefinition, string $locale, CarbonInterface $generatedAt): string
    {
        $isEnglish = str_starts_with($locale, 'en');
        $prefix = $isEnglish ? 'account-statement' : 'estratto-conto';
        $scope = $account instanceof Account
            ? str($account->name)->slug()->value()
            : ($isEnglish ? 'all-accounts' : 'tutti-i-conti');

        return sprintf(
            '%s-%s-%s-%s-%s.pdf',
            $prefix,
            $scope,
            $periodDefinition['start']->format('Ymd'),
            $periodDefinition['end']->format('Ymd'),
            $generatedAt->format('Ymd-His'),
        );
    }
}
