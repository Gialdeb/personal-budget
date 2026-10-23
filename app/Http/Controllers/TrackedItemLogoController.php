<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\ListReferenceLogosRequest;
use App\Http\Requests\Settings\PreviewTrackedItemLogoRequest;
use App\Http\Requests\Settings\UploadReferenceLogoRequest;
use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\PendingReferenceLogoService;
use App\Services\TrackedItems\WebsiteLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackedItemLogoController extends Controller
{
    public function upload(UploadReferenceLogoRequest $request, PendingReferenceLogoService $logos): JsonResponse
    {
        return response()->json($logos->upload($request->user(), $request->file('logo')));
    }

    public function discard(Request $request, PendingReferenceLogoService $logos): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:8192']]);
        $logos->discard($request->user(), $data['token']);

        return response()->json(['discarded' => true]);
    }

    public function catalog(ListReferenceLogosRequest $request): JsonResponse
    {
        $data = $request->validated();
        $logos = WebsiteIdentity::query()->where('status', 'ready')->whereNotNull('logo_path')
            ->when(filled($data['search'] ?? null), fn ($query) => $query->whereLike('domain', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['search']).'%'))
            ->orderBy('domain')->simplePaginate(24);

        return response()->json(['items' => $logos->getCollection()->filter(fn (WebsiteIdentity $identity): bool => $identity->hasStoredLogo())->map(fn (WebsiteIdentity $identity): array => ['uuid' => $identity->uuid, 'domain' => $identity->domain, 'logo_url' => $identity->logoUrl()])->values(), 'has_more' => $logos->hasMorePages()]);
    }

    public function preview(
        PreviewTrackedItemLogoRequest $request,
        WebsiteLogoService $websiteLogoService,
    ): JsonResponse {
        return response()->json($websiteLogoService->preview($request->string('website_url')->toString()));
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
