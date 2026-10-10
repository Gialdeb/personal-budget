<?php

namespace App\Services\CreditDebts;

use App\Http\Resources\CreditDebtItemResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntryOccurrence;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use Illuminate\Support\Collection;

class DerivedObligationsService
{
    public function __construct(protected SharedExpenseSettlementService $settlements) {}

    /** @return Collection<int, array<string, mixed>> */
    public function openPositions(User $user, ?array $accountIds = null, ?Collection $derived = null): Collection
    {
        $manual = CreditDebtItem::query()->forUser($user)
            ->with(['payments', 'account', 'category', 'reference'])->withCount('payments')
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
        $sum = fn (string $type): string => number_format($positions->where('type', $type)->sum('remaining_amount'), 2, '.', '');
        $overdue = $positions->filter(fn (array $item): bool => $item['due_date'] !== null && $item['due_date'] < today()->toDateString());

        return [
            'credits_remaining_total' => $sum('credit'),
            'debts_remaining_total' => $sum('debit'),
            'net_expected_total' => number_format((float) $sum('credit') - (float) $sum('debit'), 2, '.', ''),
            'overdue_count' => $overdue->count(),
            'overdue_credits_total' => number_format($overdue->where('type', 'credit')->sum('remaining_amount'), 2, '.', ''),
            'overdue_debts_total' => number_format($overdue->where('type', 'debit')->sum('remaining_amount'), 2, '.', ''),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function positions(User $user): Collection
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
                    $entry->title, number_format($occurrence->remainingPaymentCents() / 100, 2, '.', ''), $entry->currency,
                    ($occurrence->due_date ?? $occurrence->expected_date)->toDateString(),
                    route('recurring-entries.show', ['recurringEntry' => $entry->uuid, 'highlight' => $occurrence->uuid]),
                    $entry->trackedItem, $account, $entry->category,
                );
                $paid = $occurrence->allocatedPaymentCents();
                $position['total_amount'] = (string) $occurrence->expected_amount;
                $position['paid_amount'] = number_format($paid / 100, 2, '.', '');
                $position['status'] = $paid > 0 ? 'partial' : 'open';
                $position['payments_count'] = $occurrence->paymentAllocations()->valid()->count();

                return $position;
            });

        $shared = TrackedItem::query()->sharedExpenseHistory($user->id)->orderBy('name')->get()
            ->map(function (TrackedItem $counterparty) use ($user): ?array {
                $balance = $this->settlements->balanceCents($user, $counterparty);
                if ($balance === 0) {
                    return null;
                }

                return $this->position(
                    'shared:'.$counterparty->uuid, 'shared', $balance > 0 ? 'credit' : 'debit',
                    $counterparty->name, number_format(abs($balance) / 100, 2, '.', ''), 'EUR', null,
                    route('shared-expenses.index', ['counterparty' => $counterparty->uuid]), $counterparty,
                );
            })->filter();

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
