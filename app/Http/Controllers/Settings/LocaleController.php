<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $locale = $request->validated('locale');

        if ($user !== null) {
            $user->forceFill([
                'locale' => $locale,
            ])->save();
        }

        $request->session()->put('locale', $locale);
        $request->setLocale($locale);

        App::setLocale($locale);

        return back(303);
    }
}
