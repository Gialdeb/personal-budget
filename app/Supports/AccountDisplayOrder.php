<?php

namespace App\Supports;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use Illuminate\Support\Collection;

class AccountDisplayOrder
{
    /**
     * Fallback priority used for account types that are not part of
     * App\Enums\AccountTypeCodeEnum (e.g. legacy or custom type codes),
     * ensuring they are always displayed after every known type.
     */
    protected const UNKNOWN_TYPE_PRIORITY = 100;

    /**
     * Sort a collection of accounts using the application-wide, two-level
     * ordering rule:
     *
     * 1. Fixed account type priority: payment accounts, then cash accounts,
     *    then credit cards, then every other type in a stable order.
     * 2. Within the same type, the user's manually saved order
     *    (accounts.sort_order, persisted via the /settings/accounts
     *    drag & drop reorder endpoint).
     *
     * Accounts without a meaningful sort_order (or tied on every criterion)
     * fall back to a deterministic secondary order (owned before shared,
     * then name, then id) so the result is never randomly ordered.
     *
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, Account>
     */
    public static function sort(Collection $accounts): Collection
    {
        return $accounts
            ->values()
            ->sort(function (Account $left, Account $right): int {
                return static::typePriority($left) <=> static::typePriority($right)
                    ?: ((int) $left->sort_order) <=> ((int) $right->sort_order)
                    ?: static::ownershipRank($right) <=> static::ownershipRank($left)
                    ?: strcasecmp((string) $left->name, (string) $right->name)
                    ?: ((int) $left->id) <=> ((int) $right->id);
            })
            ->values();
    }

    /**
     * Resolve the fixed display priority of an account's type. Unknown or
     * missing type codes are pushed after every known type, deterministically.
     */
    public static function typePriority(Account $account): int
    {
        $code = $account->accountType?->code;

        if ($code === null) {
            return static::UNKNOWN_TYPE_PRIORITY;
        }

        return AccountTypeCodeEnum::tryFrom($code)?->displayPriority()
            ?? static::UNKNOWN_TYPE_PRIORITY;
    }

    /**
     * Owned accounts are ranked above shared accounts when every other
     * ordering criterion is tied, preserving prior behavior for merged
     * owned/shared account lists.
     */
    protected static function ownershipRank(Account $account): int
    {
        return (bool) $account->getAttribute('is_owned') ? 1 : 0;
    }
}
