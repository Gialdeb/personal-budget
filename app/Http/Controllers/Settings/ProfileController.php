<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Supports\Currency\CurrencySupport;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $canUpdateBaseCurrency = $user->canChangeBaseCurrency();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'preferences' => [
                'locale' => $user->locale,
                'format_locale' => $user->format_locale,
                'base_currency_code' => $user->base_currency_code,
                'can_update_base_currency' => $canUpdateBaseCurrency,
                'base_currency_lock_message' => $canUpdateBaseCurrency
                    ? null
                    : __('settings.profile.currency_locked_after_accounts_or_transactions'),
            ],
            'options' => [
                'locales' => collect(config('locales.supported', []))
                    ->map(
                        fn (array $locale): array => [
                            'code' => $locale['code'],
                            'label' => $locale['label'],
                        ]
                    )
                    ->values()
                    ->all(),
                'format_locales' => collect(config('currencies.format_locales', []))
                    ->map(
                        fn (string $label, string $code): array => [
                            'code' => $code,
                            'label' => $label,
                        ]
                    )
                    ->values()
                    ->all(),
                'base_currencies' => collect(app(CurrencySupport::class)->options())
                    ->map(
                        fn (array $currency): array => [
                            'code' => $currency['code'],
                            'label' => sprintf('%s (%s)', $currency['name'], $currency['code']),
                        ]
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
