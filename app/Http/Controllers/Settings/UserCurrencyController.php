<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateUserCurrencyRequest;
use App\Services\Accounts\AccountProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UserCurrencyController extends Controller
{
    public function update(
        UpdateUserCurrencyRequest $request,
        AccountProvisioningService $accountProvisioningService,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user->canChangeBaseCurrency()) {
            return back()->withErrors([
                'base_currency_code' => __('settings.profile.currency_locked_after_transactions'),
            ]);
        }

        $currencyCode = $request->validated('base_currency_code');

        DB::transaction(function () use ($accountProvisioningService, $user, $currencyCode): void {
            $user->forceFill([
                'base_currency_code' => $currencyCode,
            ])->save();

            $accountProvisioningService->syncBootstrapCashAccountCurrency($user, $currencyCode);
        });

        return back()->with('success', __('settings.profile.currency_updated'));
    }
}
