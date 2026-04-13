<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRequestRequest;
use App\Models\SupportRequest;
use App\Services\Support\StoreSupportRequest;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request, LocaleResolver $localeResolver): Response
    {
        $sourceUrl = $request->query('source_url');
        $sourceRoute = $request->query('source_route');

        return Inertia::render('settings/Support', [
            'supportCategories' => collect(SupportRequest::categories())
                ->map(fn (string $category): array => [
                    'value' => $category,
                    'label' => __("support.categories.{$category}.label"),
                    'description' => __("support.categories.{$category}.description"),
                ])
                ->all(),
            'supportContext' => [
                'source_url' => is_string($sourceUrl) ? $sourceUrl : null,
                'source_route' => is_string($sourceRoute) ? $sourceRoute : null,
                'locale' => $localeResolver->current($request),
            ],
        ]);
    }

    public function store(
        StoreSupportRequestRequest $request,
        StoreSupportRequest $storeSupportRequest,
        LocaleResolver $localeResolver,
    ): RedirectResponse {
        $storeSupportRequest->handle(
            $request->user(),
            [
                ...$request->validated(),
                'locale' => $localeResolver->current($request),
                'meta' => [
                    'user_agent' => $request->userAgent(),
                ],
            ],
        );

        return redirect()
            ->route('support.index')
            ->with('success', __('support.flash.sent'));
    }
}
