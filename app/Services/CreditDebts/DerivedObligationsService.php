<?php

namespace App\Services\CreditDebts;

use App\Http\Resources\CreditDebtItemResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntryOccurrence;
use App\Models\TrackedItem;
use App\Models\User;
use App\Support\MoneyAmount;
use Illuminate\Support\Collection;

class DerivedObligationsService
{
    public function __construct(protected CounterpartyBalanceService $counterpartyBalances) {}

    /** @return Collection<int, array<string, mixed>> */
    public function openPositions(User $user, ?array $accountIds = null, ?Collection $derived = null): Collection
    {
        $manual = CreditDebtItem::query()->forUser($user)
            ->with(['payments', 'account', 'category', 'reference', 'counterparty'])->withCount('payments')
            ->when($accountIds !== null, fn ($query) => $query->whereIn('account_id', $accountIds))
            ->get()->filter(fn (CreditDebtItem $item): bool => ! $item->isSettled())
            ->map(function (CreditDebtItem $item): array {
                $position = CreditDebtItemResource::make($item)->resolve();
                $position['source_url'] = route('credits-debts.index', ['selected' => $item->uuid]);

                return $position;
            });

        $derived ??= $this->positions($user);
        if ($accountIds !== null) {
            $accountUuids = Account::query()->whereIn('id', $accountIds)->pluck('uuid')->all();
            $derived = $derived->filter(fn (array $item): bool => $item['origin'] === 'shared'
                || in_array($item['account']['uuid'] ?? null, $accountUuids, true));
        }

        return $manual->concat($derived)->values();
    }

    /** @return array<string, mixed> */
    public function openSummary(Collection $positions): array
    {
        $sum = fn (string $type): string => MoneyAmount::decimal($positions->where('type', $type)->sum(fn (array $item): int => MoneyAmount::cents($item['remaining_amount'])));
        $overdue = $positions->filter(fn (array $item): bool => $item['due_date'] !== null && $item['due_date'] < today()->toDateString());

        return [
            'credits_remaining_total' => $sum('credit'),
            'debts_remaining_total' => $sum('debit'),
            'net_expected_total' => MoneyAmount::decimal(MoneyAmount::cents($sum('credit')) - MoneyAmount::cents($sum('debit'))),
            'overdue_count' => $overdue->count(),
            'overdue_credits_total' => MoneyAmount::decimal($overdue->where('type', 'credit')->sum(fn (array $item): int => MoneyAmount::cents($item['remaining_amount']))),
            'overdue_debts_total' => MoneyAmount::decimal($overdue->where('type', 'debit')->sum(fn (array $item): int => MoneyAmount::cents($item['remaining_amount']))),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function positions(User $user, ?array $balances = null): Collection
    {
        $recurring = RecurringEntryOccurrence::query()->openObligations()
            ->whereHas('recurringEntry', fn ($query) => $query->where('user_id', $user->id))
            ->with(['recurringEntry.account', 'recurringEntry.category', 'recurringEntry.trackedItem', 'account'])
            ->get()->map(function (RecurringEntryOccurrence $occurrence): array {
                $entry = $occurrence->recurringEntry;
                $account = $occurrence->account ?? $entry->account;
                $origin = $entry->entry_type->value === 'installment' ? 'installment' : 'recurring';

                $position = $this->position(
                    $origin.':'.$occurrence->uuid, $origin,
                    $entry->direction->value === 'income' ? 'credit' : 'debit',
                    $entry->title, MoneyAmount::decimal($occurrence->remainingPaymentCents()), $entry->currency,
                    ($occurrence->due_date ?? $occurrence->expected_date)->toDateString(),
                    route('recurring-entries.show', ['recurringEntry' => $entry->uuid, 'highlight' => $occurrence->uuid]),
                    $entry->trackedItem, $account, $entry->category,
                );
                $paid = $occurrence->allocatedPaymentCents();
                $position['total_amount'] = (string) $occurrence->expected_amount;
                $position['paid_amount'] = MoneyAmount::decimal($paid);
                $position['status'] = $paid > 0 ? 'partial' : 'open';
                $position['payments_count'] = $occurrence->paymentAllocations()->valid()->count();

                return $position;
            });

        $balances ??= $this->counterpartyBalances->forUser($user);
        $counterparties = TrackedItem::query()->ownedBy($user->id)->whereIn('uuid', array_keys($balances))->get()->keyBy('uuid');
        $shared = collect($balances)->flatMap(function (array $balances, string $uuid) use ($counterparties): array {
            $counterparty = $counterparties->get($uuid);
            if ($counterparty === null) {
                return [];
            }

            return collect($balances)->filter(fn (array $balance): bool => $balance['shared_cents'] !== 0)->map(function (array $balance) use ($counterparty): array {
                $position = $this->position(
                    'shared:'.$counterparty->uuid.($balance['currency'] === 'EUR' ? '' : ':'.$balance['currency']), 'shared', $balance['shared_cents'] > 0 ? 'credit' : 'debit',
                    $counterparty->name, MoneyAmount::decimal(abs($balance['shared_cents'])), $balance['currency'], null,
                    route('shared-expenses.index', ['counterparty' => $counterparty->uuid]), $counterparty,
                );
                $position['counterparty'] = ['uuid' => $counterparty->uuid, 'name' => $counterparty->name];

                return $position;
            })->values()->all();
        });

        return $recurring->concat($shared)->values();
    }

    /** @return array<string, mixed> */
    protected function position(string $uuid, string $origin, string $type, string $description, string $amount, string $currency, ?string $date, string $url, ?TrackedItem $reference = null, ?Account $account = null, ?Category $category = null): array
    {
        return [
            'uuid' => $uuid, 'origin' => $origin, 'read_only' => true, 'source_url' => $url,
            'type' => $type, 'description' => $description, 'total_amount' => $amount,
            'remaining_amount' => $amount, 'paid_amount' => '0.00', 'currency_code' => $currency,
            'status' => 'open', 'due_date' => $date, 'note' => null,
            'reference' => $reference ? ['uuid' => $reference->uuid, 'name' => $reference->name, 'label' => $reference->name] : null,
            'account' => $account ? ['uuid' => $account->uuid, 'value' => $account->uuid, 'name' => $account->name, 'label' => $account->name] : null,
            'category' => $category ? ['uuid' => $category->uuid, 'value' => $category->uuid, 'name' => $category->name, 'label' => $category->name] : null,
            'payments_count' => 0, 'payments' => [],
        ];
    }
}
