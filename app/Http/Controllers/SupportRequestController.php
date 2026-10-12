<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRequestRequest;
use App\Http\Requests\SupportTicketReplyRequest;
use App\Models\SupportRequest;
use App\Services\Support\StoreSupportRequest;
use App\Services\Support\SupportTicketService;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request, LocaleResolver $localeResolver, SupportTicketService $tickets): Response
    {
        $request->validate(['ticket' => ['nullable', 'uuid']]);
        $sourceUrl = $request->query('source_url');
        $sourceRoute = $request->query('source_route');

        $active = SupportRequest::where('user_id', $request->user()->id)->where('status', '!=', SupportRequest::STATUS_CLOSED)->latest()->latest('id')->first();
        $selected = $request->filled('ticket')
            ? SupportRequest::where('user_id', $request->user()->id)->where('uuid', $request->string('ticket')->toString())->firstOrFail()
            : $active;

        $history = SupportRequest::where('user_id', $request->user()->id)->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")->latest('updated_at')->latest('id')->simplePaginate(10, ['uuid', 'subject', 'category', 'status', 'app_version', 'created_at'])->withQueryString();

        $openCounts = SupportRequest::where('user_id', $request->user()->id)->where('status', '!=', SupportRequest::STATUS_CLOSED)->selectRaw('category, COUNT(*) as aggregate')->groupBy('category')->pluck('aggregate', 'category');

        return Inertia::render('settings/Support', [
            'openTicketCounts' => collect(SupportRequest::categories())->mapWithKeys(fn (string $category): array => [$category => (int) ($openCounts[$category] ?? 0)])->all(),
            'hasActiveTicket' => $active !== null,
            'currentTicket' => $selected ? $tickets->userPayload($selected->load('messages')) : null,
            'ticketHistory' => $history->items(),
            'historyNextUrl' => $history->nextPageUrl(),
            'historyPreviousUrl' => $history->previousPageUrl(),
            'appVersion' => $tickets->appVersion(),
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

    public function reply(SupportTicketReplyRequest $request, SupportRequest $supportRequest, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->reply($request->user(), $supportRequest, $request->validated('body'));

        return redirect()->route('support.index', ['ticket' => $supportRequest->uuid])->with('success', __('support.flash.replied'));
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
