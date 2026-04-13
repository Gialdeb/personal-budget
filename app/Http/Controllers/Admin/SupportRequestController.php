<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSupportRequestStatusRequest;
use App\Http\Resources\Admin\SupportRequestDetailResource;
use App\Http\Resources\Admin\SupportRequestResource;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $this->normalizeFilter(
            $request->query('status'),
            SupportRequest::statuses(),
        );
        $category = $this->normalizeFilter(
            $request->query('category'),
            SupportRequest::categories(),
        );

        $supportRequests = SupportRequest::query()
            ->with('user')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/SupportRequests/Index', [
            'supportRequests' => SupportRequestResource::collection($supportRequests),
            'filters' => [
                'status' => $status,
                'category' => $category,
            ],
            'options' => [
                'statuses' => SupportRequest::statuses(),
                'categories' => SupportRequest::categories(),
            ],
        ]);
    }

    public function show(SupportRequest $supportRequest): Response
    {
        $supportRequest->load('user');

        return Inertia::render('admin/SupportRequests/Show', [
            'supportRequest' => (new SupportRequestDetailResource($supportRequest))->resolve(),
            'statusOptions' => SupportRequest::statuses(),
        ]);
    }

    public function update(
        UpdateSupportRequestStatusRequest $request,
        SupportRequest $supportRequest,
    ): RedirectResponse {
        $supportRequest->update([
            'status' => $request->validated('status'),
        ]);

        return redirect()
            ->route('admin.support-requests.show', $supportRequest->uuid)
            ->with('success', __('admin.support_requests.flash.status_updated'));
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
