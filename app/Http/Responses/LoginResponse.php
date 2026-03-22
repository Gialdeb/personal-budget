<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->user() !== null) {
            $request->session()->put('locale', $request->user()->preferredLocale());
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(route('dashboard', absolute: false));
    }
}
