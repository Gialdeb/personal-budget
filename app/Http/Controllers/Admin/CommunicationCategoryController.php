<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCommunicationCategoryChannelsRequest;
use App\Http\Resources\Admin\CommunicationCategoryDetailResource;
use App\Http\Resources\Admin\CommunicationCategoryResource;
use App\Models\CommunicationCategory;
use App\Services\Communication\CommunicationCategoryChannelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CommunicationCategoryController extends Controller
{
    public function index(Request $request, CommunicationCategoryChannelService $channelService): Response
    {
        $search = trim((string) $request->query('search', ''));

        $categories = $channelService
            ->categoriesQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/CommunicationCategories/Index', [
            'categories' => CommunicationCategoryResource::collection($categories),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(CommunicationCategory $communicationCategory): Response
    {
        $communicationCategory->load([
            'channelTemplates' => fn ($query) => $query->with('template'),
        ]);

        return Inertia::render('admin/CommunicationCategories/Show', [
            'category' => (new CommunicationCategoryDetailResource($communicationCategory))->resolve(),
        ]);
    }

    public function updateChannels(
        UpdateCommunicationCategoryChannelsRequest $request,
        CommunicationCategory $communicationCategory,
        CommunicationCategoryChannelService $channelService,
    ): RedirectResponse {
        try {
            $channelService->syncDefaultMappings(
                $communicationCategory,
                $request->validated('channels'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('admin.communication_categories.flash.channels_saved'));
    }
}
