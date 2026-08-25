<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\PreviewTrackedItemLogoRequest;
use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\WebsiteLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackedItemLogoController extends Controller
{
    public function preview(
        PreviewTrackedItemLogoRequest $request,
        WebsiteLogoService $websiteLogoService,
    ): JsonResponse {
        $websiteUrl = $request->string('website_url')->toString();
        $normalizedUrl = $websiteLogoService->normalizeUrl($websiteUrl);
        $identity = $websiteLogoService->resolve($websiteUrl);

        return response()->json([
            'website_url' => $normalizedUrl,
            'domain' => $identity->domain,
            'logo_url' => $identity->logoUrl(),
            'status' => $identity->status,
        ]);
    }

    public function show(WebsiteIdentity $websiteIdentity): StreamedResponse
    {
        abort_unless($websiteIdentity->hasStoredLogo(), 404);

        return Storage::disk('public')->response(
            $websiteIdentity->logo_path,
            null,
            [
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Content-Type' => $websiteIdentity->logo_mime_type,
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
