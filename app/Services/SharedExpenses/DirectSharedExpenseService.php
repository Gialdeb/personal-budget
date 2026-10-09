<?php

namespace App\Services\SharedExpenses;

use App\Enums\TransactionDirectionEnum;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectSharedExpenseService
{
    public function __construct(protected AccessibleAccountsQuery $accounts, protected OperationalTransactionCategoryResolver $categories, protected SharedExpenseAllocation $allocation, protected TransactionMutationService $transactions) {}

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $counterparty = TrackedItem::query()->sharedExpenseCounterparties($user->id)->where('is_active', true)->where('uuid', $data['counterparty_uuid'])->firstOrFail();
            $allocation = $this->allocation->normalize($data['amount'], $data['paid_by'], $data['my_share'] ?? null, $data['counterparty_share'] ?? null);
            $referenceId = empty($data['reference_uuid']) ? null : TrackedItem::query()->ownedBy($user->id)->where('uuid', $data['reference_uuid'])->value('id');
            if ($data['paid_by'] === 'counterparty') {
                SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'entry_date' => $data['entry_date'], 'title' => $data['title'], 'currency' => 'EUR', 'total_cents' => $this->allocation->toCents($data['amount']), 'paid_by' => $allocation['paid_by'], 'my_share_cents' => $allocation['my_share_cents'], 'counterparty_share_cents' => $allocation['counterparty_share_cents'], 'status' => 'recorded']);

                return;
            }
            $account = $this->accounts->editable($user)->where('accounts.uuid', $data['account_uuid'])->first();
            if ($account === null) {
                throw ValidationException::withMessages(['account_uuid' => __('ui.select_an_account_221fccd7')]);
            }
            $category = $data['category_uuid'] ? $this->categories->categoriesForAccount($account)->firstWhere('uuid', $data['category_uuid']) : null;
            $transaction = $this->transactions->storeGeneratedStandardTransaction($user, $account, TransactionDirectionEnum::EXPENSE->value, (float) $data['amount'], $data['entry_date'], $category?->id, $referenceId, $data['title'], $data['notes'] ?? null);
            $transaction->update(['shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => $allocation['paid_by'], 'shared_expense_my_share_cents' => $allocation['my_share_cents'], 'shared_expense_counterparty_share_cents' => $allocation['counterparty_share_cents']]);
        });
    }
}
