<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTrackedItemRequest;
use App\Http\Requests\Settings\UpdateTrackedItemRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use App\Supports\CategoryHierarchy;
use App\Supports\TrackedItemHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrackedItemController extends Controller
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected SharedAccountTrackedItemCatalogService $sharedAccountTrackedItemCatalogService,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/TrackedItems', $payload);
    }

    public function store(StoreTrackedItemRequest $request): RedirectResponse|JsonResponse
    {
        $trackedItem = DB::transaction(function () use ($request): TrackedItem {
            $validated = $request->validated();
            $categoryIds = $validated['category_ids'] ?? [];
            unset($validated['category_ids']);

            $trackedItem = TrackedItem::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
            ]);

            $trackedItem->compatibleCategories()->sync($categoryIds);

            return $trackedItem->fresh(['compatibleCategories']);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'item' => $this->trackedItemOptionPayload($trackedItem, $request->user()->id),
            ]);
        }

        return to_route('tracked-items.edit')->with('success', __('tracked_items.flash.created'));
    }

    public function update(UpdateTrackedItemRequest $request, TrackedItem $trackedItem): RedirectResponse
    {
        $trackedItem = $this->ownedTrackedItem($request, $trackedItem);
        DB::transaction(function () use ($request, $trackedItem): void {
            $validated = $request->validated();
            $categoryIds = $validated['category_ids'] ?? [];
            unset($validated['category_ids']);

            $trackedItem->fill($validated);
            $trackedItem->save();
            $trackedItem->compatibleCategories()->sync($categoryIds);
        });

        if (! $trackedItem->is_active) {
            $descendantIds = TrackedItemHierarchy::descendantIds(
                TrackedItem::query()
                    ->ownedBy($request->user()->id)
                    ->get(['id', 'parent_id']),
                $trackedItem->id
            );

            if ($descendantIds !== []) {
                TrackedItem::query()
                    ->whereIn('id', $descendantIds)
                    ->update(['is_active' => false]);
            }
        }

        return to_route('tracked-items.edit')->with('success', __('tracked_items.flash.updated'));
    }

    public function toggleActive(Request $request, TrackedItem $trackedItem): RedirectResponse
    {
        $trackedItem = $this->ownedTrackedItem($request, $trackedItem);
        $desiredState = ! $trackedItem->is_active;

        $trackedItems = TrackedItem::query()
            ->ownedBy($request->user()->id)
            ->get(['id', 'parent_id', 'is_active']);

        if ($desiredState && $trackedItem->parent_id !== null) {
            $parent = $trackedItems->firstWhere('id', $trackedItem->parent_id);

            if ($parent !== null && ! $parent->is_active) {
                throw ValidationException::withMessages([
                    'toggle' => __('tracked_items.validation.activate_parent_first'),
                ]);
            }
        }

        $idsToUpdate = [$trackedItem->id];

        if (! $desiredState) {
            $idsToUpdate = [
                ...$idsToUpdate,
                ...TrackedItemHierarchy::descendantIds($trackedItems, $trackedItem->id),
            ];
        }

        TrackedItem::query()
            ->whereIn('id', array_values(array_unique($idsToUpdate)))
            ->update(['is_active' => $desiredState]);

        return to_route('tracked-items.edit')->with(
            'success',
            $desiredState
                ? __('tracked_items.flash.activated')
                : __('tracked_items.flash.deactivated')
        );
    }

    public function destroy(Request $request, TrackedItem $trackedItem): RedirectResponse
    {
        $trackedItem = $this->ownedTrackedItem($request, $trackedItem);
        $blockingReasons = $this->blockingReasons($trackedItem);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => __('tracked_items.validation.delete_blocked', [
                    'reasons' => implode(', ', $blockingReasons),
                ]),
            ]);
        }

        $trackedItem->delete();

        return to_route('tracked-items.edit')->with('success', __('tracked_items.flash.deleted'));
    }

    public function materialize(Request $request, Account $account): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);

        $validated = $request->validate([
            'source_tracked_item_uuid' => ['required', 'uuid'],
        ], [
            'source_tracked_item_uuid.required' => __('tracked_items.sharedBridge.validation.required'),
        ]);

        $sourceTrackedItem = TrackedItem::query()
            ->ownedBy($request->user()->id)
            ->with('compatibleCategories:id,uuid,user_id,account_id,parent_id,is_active')
            ->where('uuid', $validated['source_tracked_item_uuid'])
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->first();

        if (! $sourceTrackedItem instanceof TrackedItem) {
            throw ValidationException::withMessages([
                'source_tracked_item_uuid' => __('tracked_items.sharedBridge.validation.unavailable'),
            ]);
        }

        $existingTrackedItem = $this->sharedAccountTrackedItemCatalogService
            ->findExistingTrackedItemForSourceTrackedItem($account, $sourceTrackedItem);

        $sharedTrackedItem = DB::transaction(
            fn (): ?TrackedItem => $this->sharedAccountTrackedItemCatalogService
                ->materializeSourceTrackedItemForAccount($account, $sourceTrackedItem)
        );

        if (! $sharedTrackedItem instanceof TrackedItem) {
            throw ValidationException::withMessages([
                'source_tracked_item_uuid' => __('tracked_items.sharedBridge.validation.unavailable'),
            ]);
        }

        return to_route('tracked-items.edit')->with(
            'success',
            $existingTrackedItem instanceof TrackedItem
                ? __('tracked_items.sharedBridge.flash.reused', ['name' => $sharedTrackedItem->name])
                : __('tracked_items.sharedBridge.flash.created', ['name' => $sharedTrackedItem->name]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(int $userId): array
    {
        $trackedItems = TrackedItem::query()
            ->ownedBy($userId)
            ->with('compatibleCategories:id,uuid')
            ->withCount([
                'children',
                'transactions',
                'budgets',
                'recurringEntries',
                'scheduledEntries',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'type',
                'is_active',
            ]);

        $flatTrackedItems = collect(TrackedItemHierarchy::buildFlat($trackedItems))
            ->map(fn (array $trackedItem): array => $this->publicTrackedItemPayload($trackedItem))
            ->values()
            ->all();
        $treeTrackedItems = collect(TrackedItemHierarchy::buildTree($trackedItems))
            ->map(fn (array $trackedItem): array => $this->publicTrackedItemPayload($trackedItem))
            ->values()
            ->all();
        $typeOptions = collect($flatTrackedItems)
            ->pluck('type')
            ->filter(fn (?string $type): bool => $type !== null && $type !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'trackedItems' => [
                'tree' => $treeTrackedItems,
                'flat' => $flatTrackedItems,
                'summary' => [
                    'total_count' => count($flatTrackedItems),
                    'root_count' => collect($flatTrackedItems)->where('parent_uuid', null)->count(),
                    'active_count' => collect($flatTrackedItems)->where('is_active', true)->count(),
                    'used_count' => collect($flatTrackedItems)->where('used', true)->count(),
                    'leaf_count' => collect($flatTrackedItems)->where('children_count', 0)->count(),
                ],
            ],
            'options' => [
                'types' => $typeOptions,
                'categories' => $this->compatibleCategoryOptions($userId),
            ],
            'sharedBridge' => [
                'accounts' => $this->sharedBridgeAccounts($userId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function trackedItemOptionPayload(TrackedItem $trackedItem, int $userId): array
    {
        $trackedItems = TrackedItem::query()
            ->ownedBy($userId)
            ->with('compatibleCategories:id,uuid')
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'type',
                'is_active',
                'settings',
            ]);

        $flatItem = collect(TrackedItemHierarchy::buildFlat($trackedItems))
            ->firstWhere('id', $trackedItem->id);

        $settings = is_array($trackedItem->settings) ? $trackedItem->settings : [];

        return [
            'value' => $trackedItem->uuid,
            'uuid' => $trackedItem->uuid,
            'label' => $flatItem['full_path'] ?? $trackedItem->name,
            'group_keys' => array_values($settings['transaction_group_keys'] ?? []),
            'category_uuids' => $trackedItem->relationLoaded('compatibleCategories')
                ? $trackedItem->compatibleCategories->pluck('uuid')->filter()->values()->all()
                : $trackedItem->compatibleCategories()->pluck('categories.uuid')->filter()->values()->all(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function compatibleCategoryOptions(int $userId): array
    {
        $categories = Category::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', 'transfer');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
            ]);

        return collect(CategoryHierarchy::buildFlat($categories))
            ->map(fn (array $category): array => [
                'value' => $category['uuid'],
                'uuid' => $category['uuid'],
                'label' => $category['full_path'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sharedBridgeAccounts(int $userId): array
    {
        return $this->accessibleAccountsQuery
            ->editable($userId)
            ->get(['accounts.*'])
            ->filter(fn (Account $account): bool => $this->sharedAccountTrackedItemCatalogService->usesAccountScopedCatalog($account))
            ->values()
            ->map(function (Account $account) use ($userId): array {
                $sourceTrackedItems = $this->sharedAccountTrackedItemCatalogService
                    ->sourceTrackedItemsForAccount($account, $userId);

                return [
                    'uuid' => $account->uuid,
                    'value' => $account->uuid,
                    'label' => $account->name,
                    'source_tracked_items' => $sourceTrackedItems
                        ->map(fn (TrackedItem $trackedItem): array => [
                            'value' => $trackedItem->uuid,
                            'uuid' => $trackedItem->uuid,
                            'label' => $trackedItem->name,
                            'category_labels' => $trackedItem->compatibleCategories
                                ->pluck('name')
                                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                    'shared_items_count' => TrackedItem::query()
                        ->where('account_id', $account->id)
                        ->count(),
                ];
            })
            ->all();
    }

    protected function editableSharedAccount(Request $request, Account $account): Account
    {
        $editableAccount = $this->accessibleAccountsQuery
            ->editable($request->user())
            ->where('accounts.id', $account->id)
            ->first();

        abort_unless(
            $editableAccount instanceof Account
                && $this->sharedAccountTrackedItemCatalogService->usesAccountScopedCatalog($editableAccount),
            404,
        );

        return $editableAccount;
    }

    protected function ownedTrackedItem(Request $request, TrackedItem $trackedItem): TrackedItem
    {
        abort_unless($trackedItem->user_id === $request->user()->id, 404);

        return $trackedItem;
    }

    /**
     * @return array<int, string>
     */
    protected function blockingReasons(TrackedItem $trackedItem): array
    {
        $trackedItem->loadCount([
            'children',
            'transactions',
            'budgets',
            'recurringEntries',
            'scheduledEntries',
        ]);

        $reasons = [];

        if ($trackedItem->children_count > 0) {
            $reasons[] = $trackedItem->children_count === 1
                ? __('tracked_items.blocking_reasons.child_one')
                : __('tracked_items.blocking_reasons.child_many', ['count' => $trackedItem->children_count]);
        }

        $labels = [
            'transactions_count' => __('tracked_items.blocking_labels.transactions'),
            'budgets_count' => __('tracked_items.blocking_labels.budgets'),
            'recurring_entries_count' => __('tracked_items.blocking_labels.recurring_entries'),
            'scheduled_entries_count' => __('tracked_items.blocking_labels.scheduled_entries'),
        ];

        foreach ($labels as $countKey => $label) {
            $count = (int) $trackedItem->{$countKey};

            if ($count > 0) {
                $reasons[] = $count === 1
                    ? __('tracked_items.blocking_reasons.used_one', ['label' => $label])
                    : __('tracked_items.blocking_reasons.used_many', ['count' => $count, 'label' => $label]);
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $trackedItem
     * @return array<string, mixed>
     */
    protected function publicTrackedItemPayload(array $trackedItem): array
    {
        unset($trackedItem['id'], $trackedItem['ancestor_ids'], $trackedItem['compatible_category_ids']);

        if (isset($trackedItem['children']) && is_array($trackedItem['children'])) {
            $trackedItem['children'] = collect($trackedItem['children'])
                ->map(fn (array $child): array => $this->publicTrackedItemPayload($child))
                ->values()
                ->all();
        }

        return $trackedItem;
    }
}
