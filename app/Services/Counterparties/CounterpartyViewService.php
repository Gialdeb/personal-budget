<?php

namespace App\Services\Counterparties;

use App\Models\Account;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyOperation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySourceLink;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class CounterpartyViewService
{
    public function __construct(private readonly CounterpartyAuthority $authority, private readonly CounterpartyProjectionService $projections, private readonly CounterpartyReconciliationService $reconciliation, private readonly AccessibleAccountsQuery $accounts) {}

    public function forUser(User $user): array
    {
        if (! $this->authority->hasActivationColumn()) {
            return [];
        }

        return CounterpartyRelation::query()->where('user_id', $user->id)->when(! config('counterparty_ledger.public_enabled'), fn ($query) => $query->whereNotNull('activated_at'))->with('counterparty')->get()->map(fn ($relation): array => $this->relation($relation))->all();
    }

    public function show(User $user, CounterpartyRelation $relation): array
    {
        abort_unless($relation->user_id === $user->id && $relation->counterparty->user_id === $user->id && $relation->counterparty->account_id === null, 404);
        abort_unless(config('counterparty_ledger.public_enabled') || $relation->activated_at !== null, 404);
        $models = $relation->obligations()->get();
        $projections = $this->projections->obligations($models)->keyBy('uuid');
        $amounts = $this->authority->obligationAmounts($models);
        $identities = $models->pluck('source_identity')->merge($projections->pluck('source_identity'))->filter(fn ($identity): bool => Str::isUuid($identity))->unique();
        $manualTitles = CreditDebtItem::withTrashed()->whereIn('uuid', $identities)->pluck('description', 'uuid');
        $ledgerTitles = SharedExpenseLedgerEntry::query()->whereIn('uuid', $identities)->pluck('title', 'uuid');
        $transactionTitles = Transaction::withTrashed()->whereIn('uuid', $identities)->pluck('description', 'uuid');
        $recurringTitles = RecurringEntryOccurrence::query()->whereIn('uuid', $identities)->with('recurringEntry:id,title')->get()->mapWithKeys(fn ($occurrence): array => [$occurrence->uuid => $occurrence->recurringEntry?->title]);
        $obligations = $models->map(function (CounterpartyObligation $obligation) use ($projections, $amounts, $manualTitles, $ledgerTitles, $transactionTitles, $recurringTitles): array {
            $row = $projections[$obligation->uuid];
            $manual = $obligation->source_type->value === 'credit_debt_item';
            $title = null;
            if ($manual) {
                $title = $manualTitles->get($obligation->source_identity);
            } elseif ($row['source_type'] === 'shared_ledger_share') {
                $title = $ledgerTitles->get($row['source_identity']);
            } else {
                $title = $transactionTitles->get($row['source_identity']) ?? $recurringTitles->get($row['source_identity']);
            }

            return ['uuid' => $obligation->uuid, 'title' => $title ?? ($manual ? 'Credito/debito manuale' : 'Spesa condivisa'), 'domain' => $manual ? 'manual' : 'shared', 'direction' => $row['direction_sign'] > 0 ? 'received' : 'sent',
                'economic_date' => $row['economic_date'], 'due_date' => $row['due_date'], 'original_minor' => (string) abs($row['recognized_minor']), 'remaining_minor' => (string) $row['remaining_minor'],
                'paid_minor' => (string) $amounts[$obligation->id]['paid'], 'compensated_minor' => (string) $amounts[$obligation->id]['compensated']];
        })->sortBy(fn (array $row): string => $row['economic_date'].':'.$row['uuid'])->values()->all();
        $paymentModels = $relation->payments()->with(['account', 'revisionOf'])->orderByDesc('id')->get();
        $paymentTotals = $this->projections->paymentTotals($paymentModels);
        $returns = $this->authority->returnedForPayments($paymentModels);
        $allocations = $this->projections->activeAllocationsForPayments($paymentModels);
        $accountNames = $paymentModels->isEmpty() ? collect() : Account::query()->whereIn('uuid', $paymentTotals->pluck('document.account_uuid'))->pluck('name', 'uuid');
        $payments = $paymentModels->map(function (CounterpartyPayment $payment) use ($relation, $paymentTotals, $returns, $allocations, $accountNames): array {
            $payment->setRelation('relation', $relation);
            $document = $paymentTotals[$payment->id]['document'];
            $effective = $paymentTotals[$payment->id]['effective_minor'];
            $returned = $returns->get($payment->id, 0);
            $allocated = $paymentTotals[$payment->id]['allocated_minor'];

            return ['uuid' => $payment->uuid, 'direction' => $payment->direction->value, 'amount' => CurrencyMinorUnits::toDecimal($document['amount_minor'], $payment->relation->currency_code), 'date' => $document['economic_date'],
                'account_uuid' => $document['account_uuid'], 'account_name' => $accountNames->get($document['account_uuid'], $document['account_uuid']), 'note' => $payment->metadata['note'] ?? null,
                'effective_minor' => (string) $effective, 'returned_minor' => (string) $returned, 'allocated_minor' => (string) $allocated, 'unallocated_minor' => (string) max(0, $effective - $returned - $allocated),
                'is_return' => $payment->return_of_id !== null, 'is_legacy' => $payment->legacy_source_type !== null, 'scope' => $payment->metadata['scope'] ?? 'all', 'revision_of_uuid' => $payment->revisionOf?->uuid,
                'allocations' => $allocations->get($payment->id, collect())->map(fn ($entry): array => ['obligation_uuid' => $entry->obligation->uuid, 'amount' => CurrencyMinorUnits::toDecimal($entry->amount_minor, $payment->relation->currency_code)])->values()->all()];
        })->all();
        $compensations = $relation->ledgerEntries()->where('event_type', 'compensation')->orderByDesc('id')->get()->map(fn ($entry): array => ['uuid' => $entry->uuid, 'date' => $entry->economic_date->toDateString(), 'amount_minor' => (string) ($entry->metadata['amount_minor'] ?? 0), 'active' => ! CounterpartyLedgerEntry::query()->where('reversal_of_id', $entry->id)->exists()])->all();

        return ['relation' => $this->relation($relation), 'obligations' => $obligations, 'payments' => $payments, 'compensations' => $compensations,
            'operations' => CounterpartyOperation::query()->where('relation_id', $relation->id)->orderByDesc('id')->get(['uuid', 'kind', 'actor_id', 'created_at', 'result'])->toArray(),
            'accounts' => $this->accounts->editable($user)->where('accounts.currency_code', $relation->currency_code)->where('accounts.is_active', true)->get()->map(fn ($account): array => ['uuid' => $account->uuid, 'name' => $account->name])->all(),
            'today' => CarbonImmutable::today(config('app.timezone'))->toDateString()];
    }

    public function sourceSettlement(int $userId, string $sourceUuid, string $sourceType): ?array
    {
        return $this->sourceSettlements($userId, [$sourceUuid], $sourceType)[$sourceUuid] ?? null;
    }

    /** @param list<string> $sourceUuids
     * @return array<string, array<string, mixed>>
     */
    public function sourceSettlements(int $userId, array $sourceUuids, string $sourceType): array
    {
        if ($sourceUuids === [] || ! $this->authority->hasActivationColumn()) {
            return [];
        }
        $links = CounterpartySourceLink::query()->where('source_type', $sourceType)->whereIn('source_identity', $sourceUuids)
            ->whereHas('relation', fn ($query) => $query->where('user_id', $userId)->whereNotNull('activated_at'))->with('relation')->get();
        if ($links->isEmpty()) {
            return [];
        }
        $obligations = CounterpartyObligation::query()->whereIn('relation_id', $links->pluck('relation_id'))
            ->whereIn('economic_identity', $links->pluck('economic_identity'))->get()->keyBy(fn ($obligation): string => $obligation->relation_id.':'.$obligation->economic_identity);

        $projections = $this->projections->obligations($obligations->values())->keyBy('uuid');

        $amounts = $this->authority->obligationAmounts($obligations->values());

        return $links->mapWithKeys(function ($link) use ($obligations, $projections, $amounts): array {
            $obligation = $obligations->get($link->relation_id.':'.$link->economic_identity);
            if ($obligation === null) {
                return [];
            }
            $projection = $projections[$obligation->uuid];

            return [$link->source_identity => ['relation_uuid' => $link->relation->uuid, 'paid' => CurrencyMinorUnits::toDecimal($amounts[$obligation->id]['paid'], $link->relation->currency_code),
                'compensated' => CurrencyMinorUnits::toDecimal($amounts[$obligation->id]['compensated'], $link->relation->currency_code), 'remaining' => CurrencyMinorUnits::toDecimal($projection['remaining_minor'], $link->relation->currency_code), 'economic_group' => count($projection['component_snapshots']) > 1]];
        })->all();
    }

    private function relation(CounterpartyRelation $relation): array
    {
        $summary = $this->authority->summary($relation);
        $amountsAvailable = true;
        if ($relation->activated_at === null) {
            $legacy = app(CounterpartyBalanceService::class)->legacyBalance($relation->user, $relation->counterparty, $relation->currency_code);
            $convert = fn (int $cents): int => CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal($cents), $relation->currency_code);
            try {
                $summary = ['manual_minor' => $convert($legacy['manual_cents']), 'shared_minor' => $convert($legacy['shared_cents']), 'unallocated_minor' => 0, 'balance_minor' => $convert($legacy['total_cents'])];
            } catch (\InvalidArgumentException|\OverflowException) {
                $amountsAvailable = false;
            }
        }
        $readiness = $this->reconciliation->inspect($relation)['readiness'];

        return ['uuid' => $relation->uuid, 'counterparty_uuid' => $relation->counterparty->uuid, 'name' => $relation->counterparty->name, 'currency' => $relation->currency_code, 'precision' => $relation->minor_unit,
            'amounts_available' => $amountsAvailable, 'active' => $relation->activated_at !== null, 'readiness' => $readiness, 'can_activate' => config('counterparty_ledger.public_enabled') && $relation->activated_at === null && $readiness === 'reconciled' && $relation->minor_unit <= 2,
            'can_manage' => config('counterparty_ledger.public_enabled') && $relation->activated_at !== null && $readiness === 'reconciled',
            ...collect($summary)->map(fn (int $minor): string => (string) $minor)->all()];
    }
}
