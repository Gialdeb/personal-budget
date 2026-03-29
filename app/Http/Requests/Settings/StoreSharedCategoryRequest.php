<?php

namespace App\Http\Requests\Settings;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Supports\CategoryHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSharedCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Account|null $account */
        $account = $this->route('account');

        if (! $account instanceof Account || $this->user() === null) {
            return false;
        }

        return app(AccessibleAccountsQuery::class)->canEditAccountId($this->user(), $account->id);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->route('account');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Category::class)->where(fn ($query) => $query
                    ->where('account_id', $account->id)),
            ],
            'parent_uuid' => ['nullable', 'uuid'],
            'parent_id' => ['nullable', 'integer'],
            'direction_type' => ['required', Rule::enum(CategoryDirectionTypeEnum::class)],
            'group_type' => ['required', Rule::enum(CategoryGroupTypeEnum::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'is_active' => ['required', 'boolean'],
            'is_selectable' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Account|null $account */
        $account = $this->route('account');
        $slugSource = (string) ($this->input('slug') ?: $this->input('name'));
        $parentId = $this->filled('parent_id')
            ? (int) $this->input('parent_id')
            : ($account instanceof Account && $this->filled('parent_uuid')
                ? Category::query()
                    ->sharedForAccount($account->id)
                    ->where('uuid', (string) $this->input('parent_uuid'))
                    ->value('id')
                : null);
        $parentCategory = $account instanceof Account && $parentId
            ? Category::query()
                ->sharedForAccount($account->id)
                ->find($parentId)
            : null;

        $this->merge([
            'slug' => Str::slug($slugSource),
            'parent_uuid' => $this->filled('parent_uuid') ? (string) $this->input('parent_uuid') : null,
            'parent_id' => $parentId,
            'direction_type' => $parentCategory?->direction_type?->value
                ?? $this->input('direction_type'),
            'group_type' => $parentCategory?->group_type?->value
                ?? $this->input('group_type'),
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
            'is_active' => $this->boolean('is_active', true),
            'is_selectable' => $this->boolean('is_selectable', true),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Account|null $account */
            $account = $this->route('account');

            if (! $account instanceof Account) {
                return;
            }

            $message = $this->validateParentCategory(
                $account,
                $this->integer('parent_id') ?: null,
                null,
                $this->boolean('is_active'),
                $this->input('direction_type'),
                $this->input('group_type'),
            );

            if (($this->filled('parent_uuid') || $this->filled('parent_id')) && ! $this->integer('parent_id')) {
                $validator->errors()->add('parent_id', 'La categoria padre selezionata non è valida.');

                return;
            }

            if ($message !== null) {
                $validator->errors()->add('parent_id', $message);
            }
        });
    }

    protected function validateParentCategory(
        Account $account,
        ?int $parentId,
        ?Category $category = null,
        ?bool $isActive = null,
        ?string $directionType = null,
        ?string $groupType = null,
    ): ?string {
        if ($parentId === null) {
            return null;
        }

        $categories = Category::query()
            ->sharedForAccount($account->id)
            ->get(['id', 'parent_id', 'is_active', 'direction_type', 'group_type']);

        $parentCategory = $categories->firstWhere('id', $parentId);

        if ($parentCategory === null) {
            return 'La categoria padre selezionata non è valida.';
        }

        if ($category !== null && $parentId === $category->id) {
            return 'Una categoria non può avere sé stessa come categoria padre.';
        }

        if ($category !== null) {
            $descendantIds = CategoryHierarchy::descendantIds($categories, $category->id);

            if (in_array($parentId, $descendantIds, true)) {
                return 'Non puoi assegnare come categoria padre una sua discendente.';
            }
        }

        if ($isActive === true && ! $parentCategory->is_active) {
            return 'Una categoria attiva non può appartenere a una categoria padre disattiva.';
        }

        $parentDepth = $this->categoryDepth($categories, $parentCategory->id);

        if (($parentDepth + 1) > 2) {
            return __('categories.validation.max_depth');
        }

        if (
            $directionType !== null
            && $parentCategory->direction_type?->value !== null
            && $directionType !== $parentCategory->direction_type->value
        ) {
            return 'Una categoria figlia deve avere la stessa direzione economica della categoria padre.';
        }

        if (
            $groupType !== null
            && $parentCategory->group_type?->value !== null
            && $groupType !== $parentCategory->group_type->value
        ) {
            return 'Una categoria figlia deve avere lo stesso macrogruppo della categoria padre.';
        }

        return null;
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    protected function categoryDepth(Collection $categories, int $categoryId): int
    {
        $depth = 0;
        $current = $categories->firstWhere('id', $categoryId);
        $visited = [];

        while ($current !== null && $current->parent_id !== null) {
            if (in_array($current->id, $visited, true)) {
                break;
            }

            $visited[] = $current->id;
            $depth++;
            $current = $categories->firstWhere('id', $current->parent_id);
        }

        return $depth;
    }
}
