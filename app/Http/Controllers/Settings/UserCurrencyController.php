<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateUserCurrencyRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UserCurrencyController extends Controller
{
    public function update(UpdateUserCurrencyRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canChangeBaseCurrency()) {
            return back()->withErrors([
                'base_currency_code' => __('settings.profile.currency_locked_after_accounts_or_transactions'),
            ]);
        }

        $currencyCode = $request->validated('base_currency_code');

        DB::transaction(function () use ($user, $currencyCode): void {
            $user->forceFill([
                'base_currency_code' => $currencyCode,
            ])->save();

            Account::query()
                ->whereBelongsTo($user)
                ->get()
                ->each(function (Account $account) use ($currencyCode): void {
                    $account->forceFill([
                        'currency' => $currencyCode,
                        'currency_code' => $currencyCode,
                    ])->save();
                });
        });

        return back()->with('success', __('settings.profile.currency_updated'));
    }
}
