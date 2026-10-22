<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryTypeEnum;
use App\Models\Account;
use App\Models\CompoundRecurringEntry;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringPlanRefund;
use App\Models\RecurringPlanRefundPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyBankMutation;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use App\Services\UserYearService;
use App\Support\MoneyAmount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringPlanRefundService
{
    public function __construct(
        protected AccessibleAccountsQuery $accounts,
        protected TransactionMutationService $mutations,
        protected TransactionExchangeSnapshotService $exchange,
        protected UserYearService $years,
    ) {}

    /** @return array{plan: RecurringEntry|CompoundRecurringEntry, entries: Collection} */
    protected function plan(User $actor, string $uuid, bool $lock = false): array
    {
        $plan = CompoundRecurringEntry::query()->when($lock, fn ($query) => $query->lockForUpdate())->where('uuid', $uuid)->first();
        if ($plan !== null) {
            $entries = RecurringEntry::query()->whereHas('compoundComponent', fn ($query) => $query->where('compound_recurring_entry_id', $plan->id))->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
        } else {
            $plan = RecurringEntry::query()->when($lock, fn ($query) => $query->lockForUpdate())->where('uuid', $uuid)->firstOrFail();
            abort_if($plan->compoundComponent !== null, 404);
            $entries = collect([$plan]);
        }
        abort_unless($this->accounts->canEditAccountId($actor, $plan->account_id), 403);
        foreach ($entries as $entry) {
            abort_unless($this->accounts->canEditAccountId($actor, $entry->account_id), 403);
        }

        abort_unless($plan instanceof RecurringEntry && $plan->entry_type === RecurringEntryTypeEnum::INSTALLMENT, 403);

        return compact('plan', 'entries');
    }

    /** @return array<int, array{transaction_id: int, occurrence_id: int, amount_cents: int}> */
    protected function payments(Collection $entries, User $actor, bool $lock = false): array
    {
        if ($entries->contains(fn ($entry): bool => $entry->direction?->value !== 'expense' || $entry->salarySchedule !== null || $entry->shared_expense_counterparty_id !== null)) {
            return [];
        }
        $rows = RecurringEntryOccurrence::query()->whereIn('recurring_entry_id', $entries->pluck('id')->all())
            ->with(['paymentAllocations', 'settlementTransactions', 'convertedTransaction', 'matchedTransaction'])->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $parts = [];
        foreach ($rows as $row) {
            foreach ($row->paymentAllocations as $allocation) {
                $parts[$allocation->transaction_id][$row->id] = ($parts[$allocation->transaction_id][$row->id] ?? 0) + $allocation->amount_cents;
            }
            foreach ($row->settlementTransactions->concat(collect([$row->convertedTransaction, $row->matchedTransaction])->filter())->unique('id') as $transaction) {
                if (! $row->paymentAllocations->contains('transaction_id', $transaction->id)) {
                    $parts[$transaction->id][$row->id] = MoneyAmount::cents((string) $transaction->amount);
                }
            }
        }
        $transactions = Transaction::query()->whereIn('id', array_keys($parts))->orderBy('id')
            ->with(['refundTransaction', 'occurrencePaymentAllocations', 'creditDebtPayment', 'sharedExpenseSettlement'])
            ->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $editableIds = $this->accounts->editableIds($actor);
        $refunded = RecurringPlanRefundPayment::query()->whereIn('transaction_id', $transactions->modelKeys())->get()
            ->groupBy(fn ($part): string => $part->transaction_id.':'.$part->occurrence_id)
            ->map(fn ($parts): int => (int) $parts->sum('amount_cents'));
        $bankMutation = app(CounterpartyBankMutation::class);
        $ownedTransactionIds = $bankMutation->ownedTransactionIds($transactions->modelKeys());
        $result = [];
        foreach ($transactions as $transaction) {
            if ($transaction->status?->value !== 'confirmed' || $transaction->direction?->value !== 'expense'
                || ! in_array($transaction->kind?->value, ['manual', 'scheduled'], true)
                || $transaction->transaction_date->isAfter(today()) || $transaction->refundTransaction !== null) {
                continue;
            }
            if (! in_array((int) $transaction->account_id, $editableIds, true) || $transaction->is_transfer || $transaction->shared_expense_counterparty_id !== null
                || $transaction->creditDebtPayment !== null || $transaction->sharedExpenseSettlement !== null
                || $transaction->currency !== $entries->first()->currency) {
                return [];
            }
            if (in_array((int) $transaction->id, $ownedTransactionIds, true) && ! $bankMutation->permitted($transaction)) {
                return [];
            }
            if (array_sum($parts[$transaction->id]) > MoneyAmount::cents((string) $transaction->amount)) {
                return [];
            }
            foreach ($parts[$transaction->id] as $occurrenceId => $cents) {
                $already = $refunded->get($transaction->id.':'.$occurrenceId, 0);
                if ($cents > $already) {
                    $result[] = ['transaction_id' => $transaction->id, 'occurrence_id' => $occurrenceId, 'amount_cents' => $cents - $already];
                }
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function preview(User $actor, string $uuid): array
    {
        ['plan' => $plan, 'entries' => $entries] = $this->plan($actor, $uuid);
        $payments = $this->payments($entries, $actor);
        $accounts = $this->accounts->editable($actor)->get()
            ->filter(fn (Account $account): bool => ($account->currency_code ?? $account->currency) === $plan->currency && $account->is_active);
        $refunds = RecurringPlanRefund::query()->where('plan_uuid', $uuid)->with(['transaction.account', 'payments.transaction'])->orderByDesc('id')->get();

        return [
            'currency' => $plan->currency,
            'total_cents' => array_sum(array_column($payments, 'amount_cents')),
            'account_uuid' => $plan->account->uuid,
            'accounts' => $accounts->map(fn ($account): array => ['uuid' => $account->uuid, 'name' => $account->name])->values()->all(),
            'refunds' => $refunds->map(fn ($refund): array => [
                'uuid' => $refund->transaction->uuid, 'date' => $refund->transaction->transaction_date->toDateString(),
                'amount_cents' => $refund->amount_cents, 'account' => $refund->transaction->account->name,
                'payments' => $refund->payments->map(fn ($part): array => [
                    'uuid' => $part->transaction->uuid, 'date' => $part->transaction->transaction_date->toDateString(),
                    'amount_cents' => $part->amount_cents,
                ])->all(),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function refund(User $actor, string $uuid, array $data): Transaction
    {
        return DB::transaction(function () use ($actor, $uuid, $data): Transaction {
            ['plan' => $plan, 'entries' => $entries] = $this->plan($actor, $uuid, true);
            $existing = RecurringPlanRefund::query()->where('request_uuid', $data['request_uuid'])->first();
            if ($existing !== null) {
                abort_unless($existing->plan_uuid === $uuid && $existing->user_id === $actor->id, 403);
                if ($existing->transaction->account->uuid !== $data['account_uuid']
                    || $existing->transaction->transaction_date->toDateString() !== $data['transaction_date']
                    || $existing->amount_cents !== (int) $data['expected_total_cents']) {
                    throw ValidationException::withMessages(['refund' => __('recurring_refunds.changed')]);
                }

                return $existing->transaction;
            }
            $payments = $this->payments($entries, $actor, true);
            $total = array_sum(array_column($payments, 'amount_cents'));
            if ($total <= 0) {
                throw ValidationException::withMessages(['refund' => __('recurring_refunds.unavailable')]);
            }
            if ($total !== (int) $data['expected_total_cents']) {
                throw ValidationException::withMessages(['refund' => __('recurring_refunds.changed')]);
            }
            $account = $this->accounts->editable($actor)->where('accounts.uuid', $data['account_uuid'])->first();
            if ($account === null || ! $account->is_active || ($account->currency_code ?? $account->currency) !== $plan->currency) {
                throw ValidationException::withMessages(['account_uuid' => __('recurring_refunds.currency')]);
            }
            Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->years->ensureYearIsOpen($actor, (int) substr($data['transaction_date'], 0, 4), 'transaction_date');
            $transaction = Transaction::query()->create([
                'user_id' => $account->user_id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
                'account_id' => $account->id, 'category_id' => null, 'tracked_item_id' => $plan instanceof RecurringEntry ? $plan->tracked_item_id : null,
                'transaction_date' => $data['transaction_date'], 'value_date' => $data['transaction_date'],
                'direction' => 'income', 'kind' => 'refund', 'source_type' => 'generated', 'status' => 'confirmed',
                'amount' => MoneyAmount::decimal($total), 'currency' => $plan->currency,
                ...$this->exchange->buildForAccount($account, $total / 100, $data['transaction_date']),
                'description' => __('recurring_refunds.description', ['name' => $plan->name ?? $plan->title]),
            ]);
            $refund = RecurringPlanRefund::query()->create([
                'plan_uuid' => $uuid, 'request_uuid' => $data['request_uuid'], 'user_id' => $actor->id,
                'transaction_id' => $transaction->id, 'amount_cents' => $total,
            ]);
            $refund->payments()->createMany($payments);
            $this->mutations->recalculateAccount($account);
            $this->mutations->reconcileProcessedCreditCardCyclesForTransactions([$transaction]);

            return $transaction;
        }, 3);
    }
}
