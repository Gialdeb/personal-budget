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

    /** Called by the item update transaction with the item and reference rows locked. */
    public function confirmReference(User $user, CreditDebtItem $item, int $referenceId): void
    {
        $party = TrackedItem::query()->ownedBy($user->id)->whereNull('account_id')->whereKey($referenceId)->first();
        if ($party === null || ! $party->is_active || $item->user_id !== $user->id) {
            throw ValidationException::withMessages(['reference_id' => __('credit_debts.validation.reference_confirmation_invalid')]);
        }
        if ($reason = $this->resolver->confirmationBlockReason($item)) {
            throw ValidationException::withMessages(['reference_id' => $reason]);
        }
        $before = ['reference_id' => $item->reference_id, 'counterparty_id' => $item->counterparty_id];
        $after = ['reference_id' => $referenceId, 'counterparty_id' => $referenceId];
        $party->update(['settings' => [...($party->settings ?? []), 'economic_subject' => true]]);
        DB::table('credit_debt_items')->where('id', $item->id)->update($after);
        activity('credit_debts')->causedBy($user)->performedOn($item)->event('economic_subject_aligned')
            ->withProperties(['item_uuid' => $item->uuid, 'mode' => 'user_confirmation', 'timestamp' => now()->toIso8601String(), 'before' => $before, 'after' => $after])
            ->log('credit_debt.economic_subject_aligned');
        $item->refresh();
    }

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
                throw ValidationException::withMessages(['reference_id' => __('form_errors.subject_changed_concurrently_run_the_dry_run_again')]);
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
