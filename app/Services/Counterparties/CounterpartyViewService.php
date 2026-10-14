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
use Illuminate\Support\Facades\Schema;

class CounterpartyViewService
{
    public function __construct(private readonly CounterpartyAuthority $authority, private readonly CounterpartyProjectionService $projections, private readonly CounterpartyReconciliationService $reconciliation, private readonly AccessibleAccountsQuery $accounts) {}

    public function forUser(User $user): array
    {
        if (! Schema::hasColumn('counterparty_relations', 'activated_at')) {
            return [];
        }

        return CounterpartyRelation::query()->where('user_id', $user->id)->when(! config('counterparty_ledger.public_enabled'), fn ($query) => $query->whereNotNull('activated_at'))->with('counterparty')->get()->map(fn ($relation): array => $this->relation($relation))->all();
    }

    public function show(User $user, CounterpartyRelation $relation): array
    {
        abort_unless($relation->user_id === $user->id && $relation->counterparty->user_id === $user->id && $relation->counterparty->account_id === null, 404);
        abort_unless(config('counterparty_ledger.public_enabled') || $relation->activated_at !== null, 404);
        $obligations = $relation->obligations()->get()->map(function (CounterpartyObligation $obligation): array {
            $row = $this->projections->obligation($obligation);
            $manual = $obligation->source_type->value === 'credit_debt_item';
            $title = null;
            if ($manual) {
                $title = CreditDebtItem::withTrashed()->where('uuid', $obligation->source_identity)->value('description');
            } elseif ($row['source_type'] === 'shared_ledger_share') {
                $title = SharedExpenseLedgerEntry::query()->where('uuid', $row['source_identity'])->value('title');
            } else {
                $title = Transaction::withTrashed()->where('uuid', $row['source_identity'])->value('description') ?? RecurringEntryOccurrence::query()->where('uuid', $row['source_identity'])->with('recurringEntry')->first()?->recurringEntry?->title;
            }

            return ['uuid' => $obligation->uuid, 'title' => $title ?? ($manual ? 'Credito/debito manuale' : 'Spesa condivisa'), 'domain' => $manual ? 'manual' : 'shared', 'direction' => $row['direction_sign'] > 0 ? 'received' : 'sent',
                'economic_date' => $row['economic_date'], 'due_date' => $row['due_date'], 'original_minor' => (string) abs($row['recognized_minor']), 'remaining_minor' => (string) $row['remaining_minor'],
                'paid_minor' => (string) $this->authority->paid($obligation), 'compensated_minor' => (string) $this->authority->compensated($obligation)];
        })->sortBy(fn (array $row): string => $row['economic_date'].':'.$row['uuid'])->values()->all();
        $payments = $relation->payments()->with('account')->orderByDesc('id')->get()->map(function (CounterpartyPayment $payment): array {
            $document = $this->projections->paymentDocument($payment);
            $effective = $this->projections->effectivePayment($payment);
            $returned = $this->authority->returned($payment);
            $allocated = $this->projections->allocatedToPayment($payment);

            return ['uuid' => $payment->uuid, 'direction' => $payment->direction->value, 'amount' => CurrencyMinorUnits::toDecimal($document['amount_minor'], $payment->relation->currency_code), 'date' => $document['economic_date'],
                'account_uuid' => $document['account_uuid'], 'account_name' => Account::query()->where('uuid', $document['account_uuid'])->value('name') ?? $document['account_uuid'], 'note' => $payment->metadata['note'] ?? null,
                'effective_minor' => (string) $effective, 'returned_minor' => (string) $returned, 'allocated_minor' => (string) $allocated, 'unallocated_minor' => (string) max(0, $effective - $returned - $allocated),
                'is_return' => $payment->return_of_id !== null, 'is_legacy' => $payment->legacy_source_type !== null, 'scope' => $payment->metadata['scope'] ?? 'all', 'revision_of_uuid' => $payment->revisionOf?->uuid,
                'allocations' => $this->projections->activeAllocations($payment)->map(fn ($entry): array => ['obligation_uuid' => $entry->obligation->uuid, 'amount' => CurrencyMinorUnits::toDecimal($entry->amount_minor, $payment->relation->currency_code)])->values()->all()];
        })->all();
        $compensations = $relation->ledgerEntries()->where('event_type', 'compensation')->orderByDesc('id')->get()->map(fn ($entry): array => ['uuid' => $entry->uuid, 'date' => $entry->economic_date->toDateString(), 'amount_minor' => (string) ($entry->metadata['amount_minor'] ?? 0), 'active' => ! CounterpartyLedgerEntry::query()->where('reversal_of_id', $entry->id)->exists()])->all();

        return ['relation' => $this->relation($relation), 'obligations' => $obligations, 'payments' => $payments, 'compensations' => $compensations,
            'operations' => CounterpartyOperation::query()->where('relation_id', $relation->id)->orderByDesc('id')->get(['uuid', 'kind', 'actor_id', 'created_at', 'result'])->toArray(),
            'accounts' => $this->accounts->editable($user)->where('accounts.currency_code', $relation->currency_code)->where('accounts.is_active', true)->get()->map(fn ($account): array => ['uuid' => $account->uuid, 'name' => $account->name])->all(),
            'today' => CarbonImmutable::today(config('app.timezone'))->toDateString()];
    }

    public function sourceSettlement(int $userId, string $sourceUuid, string $sourceType): ?array
    {
        if (! Schema::hasColumn('counterparty_relations', 'activated_at')) {
            return null;
        }
        $link = CounterpartySourceLink::query()->where('source_type', $sourceType)->where('source_identity', $sourceUuid)->whereHas('relation', fn ($query) => $query->where('user_id', $userId)->whereNotNull('activated_at'))->first();
        $obligation = $link?->relation->obligations()->where('economic_identity', $link->economic_identity)->first();
        if ($obligation === null) {
            return null;
        }
        $projection = $this->projections->obligation($obligation);

        return ['relation_uuid' => $link->relation->uuid, 'paid' => CurrencyMinorUnits::toDecimal($this->authority->paid($obligation), $link->relation->currency_code),
            'compensated' => CurrencyMinorUnits::toDecimal($this->authority->compensated($obligation), $link->relation->currency_code), 'remaining' => CurrencyMinorUnits::toDecimal($projection['remaining_minor'], $link->relation->currency_code), 'economic_group' => count($projection['component_snapshots']) > 1];
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
