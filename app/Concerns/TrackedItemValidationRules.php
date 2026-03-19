<?php

namespace App\Concerns;

use App\Models\TrackedItem;
use App\Supports\TrackedItemHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TrackedItemValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function trackedItemRules(int $userId, ?TrackedItem $trackedItem = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $trackedItem === null
                    ? Rule::unique(TrackedItem::class)->where('user_id', $userId)
                    : Rule::unique(TrackedItem::class)->where('user_id', $userId)->ignore($trackedItem->id),
            ],
            'parent_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50', 'regex:/^[\pL\pN\s\-_]+$/u'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function validateParentTrackedItem(
        int $userId,
        ?int $parentId,
        ?TrackedItem $trackedItem = null,
        ?bool $isActive = null
    ): ?string {
        if ($parentId === null) {
            return null;
        }

        $trackedItems = TrackedItem::query()
            ->ownedBy($userId)
            ->get(['id', 'parent_id', 'is_active']);

        $parentTrackedItem = $trackedItems->firstWhere('id', $parentId);

        if ($parentTrackedItem === null) {
            return "L'elemento padre selezionato non è valido.";
        }

        if ($trackedItem !== null && $parentId === $trackedItem->id) {
            return 'Un elemento da tracciare non può avere sé stesso come padre.';
        }

        if ($trackedItem !== null) {
            $descendantIds = TrackedItemHierarchy::descendantIds($trackedItems, $trackedItem->id);

            if (in_array($parentId, $descendantIds, true)) {
                return 'Non puoi assegnare come padre un elemento suo discendente.';
            }
        }

        if ($isActive === true && ! $parentTrackedItem->is_active) {
            return 'Un elemento attivo non può appartenere a un padre disattivato.';
        }

        return null;
    }
}
