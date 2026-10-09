<?php

namespace App\Services\SharedExpenses;

use Illuminate\Validation\ValidationException;

class SharedExpenseAllocation
{
    public const PAID_BY_SELF = 'self';

    public const PAID_BY_COUNTERPARTY = 'counterparty';

    /**
     * @return array{paid_by: string, my_share_cents: int, counterparty_share_cents: int}
     */
    public function normalize(string $amount, ?string $paidBy, mixed $myShare, mixed $counterpartyShare): array
    {
        $totalCents = $this->toCents($amount);
        $paidBy = $paidBy ?: self::PAID_BY_SELF;

        if (! in_array($paidBy, [self::PAID_BY_SELF, self::PAID_BY_COUNTERPARTY], true)) {
            throw ValidationException::withMessages(['shared_expense_paid_by' => __('ui.the_shared_expense_payer_is_invalid_a42baed3')]);
        }

        if ($myShare === null && $counterpartyShare === null) {
            $myShareCents = intdiv($totalCents, 2);
            $counterpartyShareCents = $totalCents - $myShareCents;
        } elseif ($myShare === null || $counterpartyShare === null) {
            throw ValidationException::withMessages(['shared_expense_my_share' => __('ui.enter_both_shares_of_the_shared_expense_0a16aba1')]);
        } else {
            $myShareCents = $this->toCents((string) $myShare);
            $counterpartyShareCents = $this->toCents((string) $counterpartyShare);
        }

        if ($myShareCents < 0 || $counterpartyShareCents < 0 || $myShareCents + $counterpartyShareCents !== $totalCents) {
            throw ValidationException::withMessages(['shared_expense_my_share' => __('ui.shares_must_be_nonnegative_and_add_up_exactly_to_the_total_d7798047')]);
        }

        return [
            'paid_by' => $paidBy,
            'my_share_cents' => $myShareCents,
            'counterparty_share_cents' => $counterpartyShareCents,
        ];
    }

    public function toCents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw ValidationException::withMessages(['amount' => __('ui.the_amount_must_have_at_most_two_decimal_places_2198aed4')]);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /**
     * A positive result means that the counterparty owes the user; a negative
     * result means that the user owes the counterparty.
     *
     * @param  array{paid_by: string, my_share_cents: int, counterparty_share_cents: int}  $allocation
     */
    public function balanceEffectCents(array $allocation): int
    {
        return $allocation['paid_by'] === self::PAID_BY_SELF
            ? $allocation['counterparty_share_cents']
            : -$allocation['my_share_cents'];
    }
}
