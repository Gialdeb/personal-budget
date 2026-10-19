<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOnboardingRequest;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function update(UpdateOnboardingRequest $request, OnboardingService $onboarding): JsonResponse
    {
        return response()->json($onboarding->update(
            $request->user(),
            $request->validated('action'),
            (bool) $request->validated('quick_start_dismissed', false),
            $request->validated('section', 'dashboard'),
        ));
    }
}
