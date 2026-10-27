<?php

namespace App\Services\Counterparties;

use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartySourceLink;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\SharedExpenses\SharedOccurrenceCounterpartyResolver;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Illuminate\Validation\ValidationException;

/** Read-only normalization of actual legacy effects. */
class SharedSourceProjector
{
    public function __construct(
        private readonly AccessibleAccountsQuery $accounts,
        private readonly SharedOccurrenceCounterpartyResolver $counterparties,
    ) {}

    public function sourceType(Transaction|SharedExpenseLedgerEntry $source): string
    {
        return $source instanceof Transaction ? 'shared_transaction_share' : 'shared_ledger_share';
    }

    /** @return array<string, mixed> */
    public function describe(Transaction|SharedExpenseLedgerEntry $source, bool $deleted = false): array
    {
        $type = $this->sourceType($source);
        $link = CounterpartySourceLink::query()->where('source_type', $type)->where('source_identity', $source->uuid)->first();
        $partyId = $source instanceof Transaction ? $source->shared_expense_counterparty_id : $source->counterparty_id;
        $party = TrackedItem::query()->find($partyId ?? $link?->relation->counterparty_id);
        if ($party === null || $party->account_id !== null) {
            throw ValidationException::withMessages(['identity' => __('form_errors.a_personal_counterparty_is_required')]);
        }
        if ($source instanceof SharedExpenseLedgerEntry && $source->user_id !== $party->user_id) {
            throw ValidationException::withMessages(['ownership' => __('form_errors.external_shared_source_and_personal_counterparty_have_different_owners')]);
        }
        $currency = $source->currency;
        $knownIdentity = null;
        if ($link === null) {
            $roots = CounterpartyLedgerEntry::query()->where('source_type', $type)->where('source_identity', $source->uuid)->whereNotNull('obligation_id')
                ->whereHas('relation', fn ($query) => $query->where('user_id', $party->user_id)->where('counterparty_id', $party->id)->where('currency_code', $currency))
                ->distinct()->pluck('obligation_id');
            if ($roots->count() > 1) {
                throw ValidationException::withMessages(['identity' => __('form_errors.the_source_already_belongs_to_multiple_economic_obligations')]);
            }
            $knownIdentity = $roots->isEmpty() ? null : CounterpartyObligation::query()->findOrFail($roots->first())->economic_identity;
        }
        if ($link !== null && ($link->relation->counterparty_id !== $party->id || $link->relation->user_id !== $party->user_id || $link->relation->currency_code !== $currency)) {
            throw ValidationException::withMessages(['identity' => __('form_errors.counterparty_or_currency_changed_on_an_already_mapped_shared_fact')]);
        }
        $effect = 0;
        if ($source instanceof Transaction) {
            if (! in_array($source->account_id, $this->accounts->ids($party->user_id), true)) {
                throw ValidationException::withMessages(['ownership' => __('form_errors.the_shared_bank_document_is_not_accessible_to_the_personal_counterparty_owner')]);
            }
            $source->load('refundTransaction');
            if ($source->sharedExpenseSettlement()->exists() || $source->creditDebtPayment()->exists()) {
                throw ValidationException::withMessages(['source' => __('form_errors.the_same_bank_document_is_marked_as_both_expense_and_payment')]);
            }
            if (! $deleted && ! $source->trashed() && $source->status === TransactionStatusEnum::CONFIRMED && $partyId !== null) {
                $total = MoneyAmount::cents((string) $source->amount);
                $share = ($source->shared_expense_paid_by ?? 'self') === 'self'
                    ? ($source->shared_expense_counterparty_share_cents ?? $total - intdiv($total, 2))
                    : -($source->shared_expense_my_share_cents ?? intdiv($total, 2));
                $refund = $source->refundTransaction;
                $active = max(0, $total - ($refund?->status === TransactionStatusEnum::CONFIRMED ? MoneyAmount::cents((string) $refund->amount) : 0));
                $effect = MoneyAmount::proportional($share, $active, $total);
            }
            $date = $source->transaction_date->toDateString();
        } else {
            if (! $deleted && $source->isRecorded()) {
                $effect = $source->paid_by === 'self' ? $source->counterparty_share_cents : -$source->my_share_cents;
            }
            $date = $source->entry_date->toDateString();
        }
        $occurrences = collect();
        if ($source->recurring_entry_occurrence_id !== null) {
            $occurrences->push(RecurringEntryOccurrence::withHistoricalPlan()->findOrFail($source->recurring_entry_occurrence_id));
        }
        if ($source instanceof Transaction) {
            $occurrences = $occurrences->concat(RecurringEntryOccurrence::withHistoricalPlan()->where('converted_transaction_id', $source->id)->orWhere('matched_transaction_id', $source->id)->get());
        }
        $occurrences = $occurrences->unique('id');
        if ($occurrences->count() > 1) {
            throw ValidationException::withMessages(['identity' => __('form_errors.one_shared_document_is_attached_to_multiple_occurrences')]);
        }
        foreach ($occurrences as $occurrence) {
            $historical = $this->counterparties->historicalIdentity($occurrence);
            if ($currency !== $occurrence->recurringEntry->currency || ($historical !== null && ($historical['counterparty_id'] !== $party->id || $historical['currency'] !== $currency))) {
                throw ValidationException::withMessages(['identity' => __('form_errors.the_source_conflicts_with_the_economic_identity_of_its_occurrence')]);
            }
        }
        $occurrenceIdentity = $occurrences->isEmpty() ? null : 'shared-occurrence:'.$occurrences->first()->uuid;
        if ($occurrences->isNotEmpty() && $occurrences->first()->recurringEntry->user_id !== $party->user_id) {
            throw ValidationException::withMessages(['ownership' => __('form_errors.occurrence_and_personal_counterparty_have_different_owners')]);
        }
        $persistentIdentity = $link?->economic_identity ?? $knownIdentity;
        if ($persistentIdentity !== null && $occurrenceIdentity !== null && $persistentIdentity !== $occurrenceIdentity) {
            throw ValidationException::withMessages(['identity' => __('form_errors.occurrence_identity_conflicts_with_the_existing_source_mapping')]);
        }

        $documentIdentity = $source->uuid;
        $sourceIdentity = $source->uuid;
        $metadata = ['legacy_source_uuid' => $source->uuid, 'legacy_status' => $deleted ? 'deleted' : ($source->status instanceof \BackedEnum ? $source->status->value : $source->status), 'refund_uuid' => $source instanceof Transaction ? $source->refundTransaction?->uuid : null];
        if ($source instanceof Transaction && $source->kind === TransactionKindEnum::SCHEDULED && $source->recurring_entry_occurrence_id !== null && ($source->shared_expense_paid_by ?? 'self') === 'self') {
            $components = Transaction::withTrashed()->where('recurring_entry_occurrence_id', $source->recurring_entry_occurrence_id)->where('kind', 'scheduled')
                ->where('shared_expense_counterparty_id', $party->id)->where('currency', $currency)->where('shared_expense_paid_by', 'self')->whereIn('account_id', $this->accounts->ids($party->user_id))->with('refundTransaction')->orderBy('uuid')->get();
            $componentSnapshots = [];
            $effect = 0;
            foreach ($components as $component) {
                $total = MoneyAmount::cents((string) $component->amount);
                $active = $component->trashed() || $component->status !== TransactionStatusEnum::CONFIRMED || ($deleted && $component->uuid === $source->uuid) ? 0
                    : max(0, $total - ($component->refundTransaction?->status === TransactionStatusEnum::CONFIRMED ? MoneyAmount::cents((string) $component->refundTransaction->amount) : 0));
                $share = MoneyAmount::proportional($component->shared_expense_counterparty_share_cents ?? $total - intdiv($total, 2), $active, $total);
                $effect = CurrencyMinorUnits::add($effect, $share);
                $componentSnapshots[$component->uuid] = CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal($share), $currency);
            }
            $sourceIdentity = $occurrences->first()->uuid;
            $date = $components->min(fn (Transaction $component): string => $component->transaction_date->toDateString()) ?? $date;
            $metadata = ['legacy_source_uuid' => $sourceIdentity, 'legacy_status' => 'recognized_components', 'refund_uuid' => null, 'component_snapshots' => $componentSnapshots];
        }

        return ['source_type' => $type, 'source_identity' => $sourceIdentity, 'document_identity' => $documentIdentity, 'economic_identity' => $persistentIdentity ?? $occurrenceIdentity ?? 'shared-fact:'.$source->uuid,
            'user_id' => $party->user_id, 'counterparty_id' => $party->id, 'currency_code' => $currency,
            'signed_minor' => CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal($effect), $currency), 'economic_date' => $date,
            'metadata' => $metadata];
    }

    /** @return array<string, mixed> */
    public function settlement(SharedExpenseSettlement $source, bool $deleted = false): array
    {
        $transaction = Transaction::withTrashed()->with(['account', 'refundTransaction'])->find($source->transaction_id);
        if ($transaction === null || $transaction->account === null || $transaction->currency !== 'EUR' || $transaction->account->currency_code !== 'EUR'
            || $transaction->shared_expense_counterparty_id !== null || $transaction->creditDebtPayment()->exists() || $transaction->is_transfer
            || $transaction->user_id !== $transaction->account->user_id || MoneyAmount::cents((string) $transaction->amount) !== $source->amount_cents
            || $transaction->direction->value !== ($source->direction === 'counterparty_to_me' ? 'income' : 'expense')) {
            throw ValidationException::withMessages(['settlement' => __('form_errors.legacy_settlement_has_an_invalid_or_missing_bank_document')]);
        }
        $effective = $deleted || $transaction->trashed() || $transaction->status !== TransactionStatusEnum::CONFIRMED ? 0
            : max(0, $source->amount_cents - ($transaction->refundTransaction?->status === TransactionStatusEnum::CONFIRMED ? MoneyAmount::cents((string) $transaction->refundTransaction->amount) : 0));

        return ['source_type' => 'shared_expense_settlement', 'source_identity' => $source->uuid, 'user_id' => $source->user_id,
            'counterparty_id' => $source->counterparty_id, 'currency_code' => 'EUR', 'effective_minor' => $effective,
            'signed_minor' => $effective * ($source->direction === 'counterparty_to_me' ? -1 : 1), 'economic_date' => $source->settled_at->toDateString(),
            'transaction_uuid' => $transaction->uuid, 'account_uuid' => $transaction->account->uuid];
    }
}
