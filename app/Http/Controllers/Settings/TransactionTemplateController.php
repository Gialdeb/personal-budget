<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReorderTransactionTemplatesRequest;
use App\Http\Requests\Settings\SaveTransactionTemplateRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\TransactionTemplate;
use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransactionTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('settings/QuickActions', [
            'templates' => TransactionTemplate::where('user_id', $userId)->with(['account', 'category', 'trackedItem', 'sharedCounterparty'])->orderBy('sort_order')->orderBy('name')->get()->map(fn (TransactionTemplate $template): array => [
                ...$template->prefill(),
                'category_name' => $template->category?->displayName(),
                'tracked_item_name' => $template->trackedItem?->name,
                'tracked_item_logo_url' => $template->trackedItem?->logoUrl(),
            ]),
            'accounts' => Account::where('user_id', $userId)->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
            'categories' => Category::where('user_id', $userId)->where('is_active', true)->where('is_selectable', true)->with(['parent:id,uuid,name', 'account:id,uuid'])->orderBy('name')->get()->map(fn (Category $category): array => [
                'uuid' => $category->uuid,
                'name' => $category->displayName(),
                'full_path' => $category->parent ? $category->parent->name.' › '.$category->displayName() : $category->displayName(),
                'parent_uuid' => $category->parent?->uuid,
                'account_uuid' => $category->account?->uuid,
                'direction' => $category->direction_type?->value,
                'icon' => $category->icon,
                'color' => $category->color,
                'group_key' => $category->group_type?->value,
            ]),
            'references' => TrackedItem::where('user_id', $userId)->where('is_active', true)->with(['websiteIdentity', 'compatibleCategories:id,uuid'])->orderBy('name')->get()->map(fn (TrackedItem $trackedItem): array => [
                'uuid' => $trackedItem->uuid,
                'name' => $trackedItem->name,
                'logo_url' => $trackedItem->logoUrl(),
                'is_shared_expense_counterpart' => $trackedItem->is_shared_expense_counterpart,
                'category_uuids' => $trackedItem->compatibleCategories->pluck('uuid'),
                'group_keys' => $trackedItem->settings['transaction_group_keys'] ?? [],
            ]),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json(TransactionTemplate::where('user_id', $userId)
            ->where('is_active', true)
            ->with([
                'category' => fn ($query) => $query->where('user_id', $userId)->with('parent:id,uuid,name'),
                'account' => fn ($query) => $query->where('user_id', $userId),
                'trackedItem' => fn ($query) => $query->where('user_id', $userId)->with('websiteIdentity'),
            ])
            ->orderBy('sort_order')->orderBy('name')
            ->get(['uuid', 'name', 'icon', 'color', 'category_id', 'tracked_item_id', 'account_id', 'shared_enabled'])
            ->map(fn (TransactionTemplate $template): array => [
                'uuid' => $template->uuid,
                'name' => $template->name,
                'icon' => $template->icon,
                'color' => $template->color,
                'shared_enabled' => $template->shared_enabled,
                'account_name' => $template->account?->name,
                'category' => $template->category ? [
                    'uuid' => $template->category->uuid,
                    'name' => $template->category->displayName(),
                    'full_path' => $template->category->parent ? $template->category->parent->name.' › '.$template->category->displayName() : $template->category->displayName(),
                    'icon' => $template->category->icon,
                    'color' => $template->category->color,
                ] : null,
                'reference' => $template->trackedItem ? [
                    'name' => app(TrackedItemIdentityService::class)->presentation($template->trackedItem)['label'],
                    'logo_url' => $template->trackedItem->logoUrl(),
                ] : null,
            ]));
    }

    public function store(SaveTransactionTemplateRequest $request): RedirectResponse
    {
        $data = $request->templateData();
        app(TrackedItemIdentityService::class)->withNewReferences([
            'tracked_item_id' => $data['tracked_item_id'],
            'shared_counterparty_id' => $data['shared_counterparty_id'],
        ], function () use ($request, $data): void {
            $template = new TransactionTemplate($data);
            $template->user_id = $request->user()->id;
            $template->sort_order = (int) TransactionTemplate::where('user_id', $request->user()->id)->max('sort_order') + 1;
            $template->save();
        });

        return to_route('transaction-templates.index')->with('success', __('ui.quick_action_created_0d6edc71'));
    }

    public function update(SaveTransactionTemplateRequest $request, TransactionTemplate $transactionTemplate): RedirectResponse
    {
        abort_unless($transactionTemplate->user_id === $request->user()->id, 404);
        $transactionTemplate->update($request->templateData());

        return to_route('transaction-templates.index')->with('success', __('ui.quick_action_updated_a252b7ad'));
    }

    public function reorder(ReorderTransactionTemplatesRequest $request): RedirectResponse
    {
        $submittedUuids = collect($request->validated('uuids'));

        DB::transaction(function () use ($request, $submittedUuids): void {
            $templates = TransactionTemplate::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->get(['id', 'uuid']);

            abort_unless($templates->pluck('uuid')->sort()->values()->all() === $submittedUuids->sort()->values()->all(), 422);

            $positions = $submittedUuids->flip();

            foreach ($templates as $template) {
                $template->update(['sort_order' => $positions->get($template->uuid)]);
            }
        });

        return to_route('transaction-templates.index');
    }

    public function toggleActive(Request $request, TransactionTemplate $transactionTemplate): RedirectResponse
    {
        abort_unless($transactionTemplate->user_id === $request->user()->id, 404);
        $transactionTemplate->update(['is_active' => ! $transactionTemplate->is_active]);

        return back();
    }

    public function destroy(Request $request, TransactionTemplate $transactionTemplate): RedirectResponse
    {
        abort_unless($transactionTemplate->user_id === $request->user()->id, 404);
        $transactionTemplate->delete();

        return to_route('transaction-templates.index')->with('success', __('ui.quick_action_deleted_65d2c0f5'));
    }
}
