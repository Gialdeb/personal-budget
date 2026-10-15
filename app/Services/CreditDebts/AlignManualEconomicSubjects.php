<?php

namespace App\Services\CreditDebts;

use App\Models\CreditDebtItem;
use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlignManualEconomicSubjects
{
    public function __construct(private readonly ManualEconomicSubjectResolver $resolver) {}

    /** @return array<string, mixed> */
    public function item(User $user, string $uuid, bool $apply = false): array
    {
        return DB::transaction(function () use ($user, $uuid, $apply): array {
            $preview = CreditDebtItem::withTrashed()->forUser($user)->where('uuid', $uuid)->firstOrFail();
            if ($apply) {
                TrackedItem::query()->whereIn('id', array_filter([$preview->reference_id, $preview->counterparty_id]))->orderBy('id')->lockForUpdate()->get();
            }
            $item = CreditDebtItem::withTrashed()->forUser($user)->whereKey($preview->id)->when($apply, fn ($query) => $query->lockForUpdate())->firstOrFail();
            $plan = $this->resolver->classify($item);
            if ($apply && $plan['status'] === 'safe' && ($item->reference_id !== $preview->reference_id || $item->counterparty_id !== $preview->counterparty_id)) {
                throw ValidationException::withMessages(['reference_id' => 'Subject changed concurrently; run the dry-run again.']);
            }
            $before = ['reference_id' => $item->reference_id, 'counterparty_id' => $item->counterparty_id];
            $after = $plan['status'] === 'safe' ? ['reference_id' => $plan['target'], 'counterparty_id' => $plan['target']] : $before;
            $report = ['item_uuid' => $item->uuid, 'mode' => $apply ? 'apply' : 'dry-run', 'timestamp' => now()->toIso8601String(), ...$plan, 'before' => $before, 'after' => $after];
            if ($apply && $plan['status'] === 'safe') {
                DB::table('credit_debt_items')->where('id', $item->id)->update($after);
                $report['status'] = 'applied';
                activity('credit_debts')->causedBy($user)->performedOn($item)->event('economic_subject_aligned')->withProperties($report)->log('credit_debt.economic_subject_aligned');
            }

            return $report;
        });
    }
}
