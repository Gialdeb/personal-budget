<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateImpersonationConsentRequest;
use Illuminate\Http\RedirectResponse;

class ImpersonationConsentController extends Controller
{
    public function update(UpdateImpersonationConsentRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'is_impersonable' => $request->boolean('is_impersonable'),
        ])->save();

        return back()->with('success', __('settings.profile.impersonation_consent_updated'));
    }
}
