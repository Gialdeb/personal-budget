<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTrackedItemRequest;
use App\Http\Requests\Settings\UpdateTrackedItemRequest;
use App\Models\TrackedItem;
use App\Supports\TrackedItemHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrackedItemController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/TrackedItems', $payload);
    }

    public function store(StoreTrackedItemRequest $request): RedirectResponse
    {
        TrackedItem::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return to_route('tracked-items.edit')->with('success', 'Elemento da tracciare creato correttamente.');
    }

    public function update(UpdateTrackedItemRequest $request, TrackedItem $trackedItem): RedirectResponse
    {
        $trackedItem = $this->ownedTrackedItem($request, $trackedItem);

        $trackedItem->fill($request->validated());
        $trackedItem->save();

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

        return to_route('tracked-items.edit')->with('success', 'Elemento da tracciare aggiornato correttamente.');
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
                    'toggle' => "Attiva prima l'elemento padre per riattivare questo elemento.",
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
                ? 'Elemento da tracciare attivato correttamente.'
                : 'Elemento da tracciare disattivato correttamente.'
        );
    }

    public function destroy(Request $request, TrackedItem $trackedItem): RedirectResponse
    {
        $trackedItem = $this->ownedTrackedItem($request, $trackedItem);
        $blockingReasons = $this->blockingReasons($trackedItem);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => 'Questo elemento non può essere eliminato: '
                    .implode(', ', $blockingReasons)
                    .'. Disattivalo invece per conservarne lo storico.',
            ]);
        }

        $trackedItem->delete();

        return to_route('tracked-items.edit')->with('success', 'Elemento da tracciare eliminato correttamente.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(int $userId): array
    {
        $trackedItems = TrackedItem::query()
            ->ownedBy($userId)
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
                'user_id',
                'parent_id',
                'name',
                'slug',
                'type',
                'is_active',
            ]);

        $flatTrackedItems = TrackedItemHierarchy::buildFlat($trackedItems);
        $treeTrackedItems = TrackedItemHierarchy::buildTree($trackedItems);
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
                    'root_count' => collect($flatTrackedItems)->where('parent_id', null)->count(),
                    'active_count' => collect($flatTrackedItems)->where('is_active', true)->count(),
                    'used_count' => collect($flatTrackedItems)->where('used', true)->count(),
                    'leaf_count' => collect($flatTrackedItems)->where('children_count', 0)->count(),
                ],
            ],
            'options' => [
                'types' => $typeOptions,
            ],
        ];
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
                ? 'ha un elemento figlio'
                : "ha {$trackedItem->children_count} elementi figli";
        }

        $labels = [
            'transactions_count' => 'transazioni',
            'budgets_count' => 'budget',
            'recurring_entries_count' => 'ricorrenze',
            'scheduled_entries_count' => 'scadenze pianificate',
        ];

        foreach ($labels as $countKey => $label) {
            $count = (int) $trackedItem->{$countKey};

            if ($count > 0) {
                $reasons[] = $count === 1
                    ? "è usato in 1 {$label}"
                    : "è usato in {$count} {$label}";
            }
        }

        return $reasons;
    }
}
