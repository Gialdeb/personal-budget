<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSupportRequestStatusRequest;
use App\Http\Requests\SupportTicketReplyRequest;
use App\Http\Resources\Admin\SupportRequestDetailResource;
use App\Http\Resources\Admin\SupportRequestResource;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['user' => ['nullable', 'uuid'], 'version' => ['nullable', 'string', 'max:80'], 'search' => ['nullable', 'string', 'max:150']]);
        $status = $this->normalizeFilter(
            $request->query('status', 'open'),
            [...SupportRequest::statuses(), 'open', 'all'],
        ) ?? 'open';
        $category = $this->normalizeFilter(
            $request->query('category'),
            SupportRequest::categories(),
        );

        $search = mb_substr(trim((string) $request->query('search', '')), 0, 150);
        $version = mb_substr(trim((string) $request->query('version', '')), 0, 80);
        $user = is_string($request->query('user')) ? $request->query('user') : '';
        $baseQuery = SupportRequest::query()
            ->when($user !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('uuid', $user)))
            ->when($version !== '', fn ($query) => $query->where('app_version', $version));
        $openCounts = (clone $baseQuery)->where('status', '!=', SupportRequest::STATUS_CLOSED)->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');
        $supportRequests = (clone $baseQuery)
            ->with('user')
            ->when($status === 'open', fn ($query) => $query->where('status', '!=', SupportRequest::STATUS_CLOSED))
            ->when(in_array($status, SupportRequest::statuses(), true), fn ($query) => $query->where('status', $status))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where(fn ($users) => $users->whereLike('name', '%'.$search.'%')->orWhereLike('surname', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%'))))
            ->when($version !== '', fn ($query) => $query->where('app_version', $version))
            ->orderByRaw("CASE WHEN status = 'waiting_admin' THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/SupportRequests/Index', [
            'supportRequests' => SupportRequestResource::collection($supportRequests),
            'filters' => [
                'status' => $status,
                'user' => $user,
                'search' => $search,
                'version' => $version,
                'category' => $category,
            ],
            'options' => [
                'statuses' => SupportRequest::statuses(),
                'categories' => SupportRequest::categories(),
                'openCounts' => collect(SupportRequest::categories())->mapWithKeys(fn ($category) => [$category => (int) ($openCounts[$category] ?? 0)]),
                'users' => User::query()->whereHas('supportRequests')->orderBy('name')->get(['uuid', 'name', 'surname', 'email'])->map(fn ($user) => ['value' => $user->uuid, 'label' => trim($user->name.' '.$user->surname).' · '.$user->email]),
                'versions' => SupportRequest::query()->whereNotNull('app_version')->distinct()->orderBy('app_version')->pluck('app_version')->map(fn ($version) => ['value' => $version, 'label' => $version]),
            ],
        ]);
    }

    public function show(SupportRequest $supportRequest): Response
    {
        $supportRequest->load(['user', 'messages.admin']);

        return Inertia::render('admin/SupportRequests/Show', [
            'supportRequest' => (new SupportRequestDetailResource($supportRequest))->resolve(),
            'statusOptions' => [SupportRequest::STATUS_NEW, SupportRequest::STATUS_CLOSED],
        ]);
    }

    public function update(
        UpdateSupportRequestStatusRequest $request,
        SupportRequest $supportRequest,
        SupportTicketService $tickets,
    ): RedirectResponse {
        $tickets->changeStatus($request->user(), $supportRequest, $request->validated('status'));

        return redirect()
            ->route('admin.support-requests.show', $supportRequest->uuid)
            ->with('success', __('admin.support_requests.flash.status_updated'));
    }

    public function reply(SupportTicketReplyRequest $request, SupportRequest $supportRequest, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->reply($request->user(), $supportRequest, $request->validated('body'), true);

        return redirect()->route('admin.support-requests.show', $supportRequest->uuid)->with('success', __('support.flash.replied'));
    }

    /**
     * @param  array<int, string>  $allowedValues
     */
    protected function normalizeFilter(mixed $value, array $allowedValues): ?string
    {
        return is_string($value) && in_array($value, $allowedValues, true)
            ? $value
            : null;
    }
}
