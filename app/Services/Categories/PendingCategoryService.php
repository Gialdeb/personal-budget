<?php

namespace App\Services\Categories;

use App\Http\Requests\Settings\StoreCategoryRequest;
use App\Http\Requests\Settings\StoreSharedCategoryRequest;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PendingCategoryService
{
    public const PREFIX = 'pending-category:';

    /** @return array<string, mixed> */
    public function preview(Request $request): array
    {
        $account = $this->account($request, (string) $request->input('inline_account_uuid'));
        $shared = $request->routeIs('shared-categories.store');
        if ($shared && ($request->route('account')?->id !== $account->id || ! app(SharedAccountCategoryTaxonomyService::class)->isSharedAccount($account))) {
            $this->invalid();
        }
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'], 'parent_uuid' => ['nullable', 'string', 'max:30000'],
            'direction_type' => ['required', 'in:income,expense,mixed'], 'group_type' => ['required', 'in:income,expense,bill,debt,saving,transfer'],
            'slug' => ['nullable', 'string', 'max:150'], 'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'is_active' => ['required', 'boolean'], 'is_selectable' => ['required', 'boolean'],
        ])->validate();
        $parentUuid = $data['parent_uuid'] ?? null;
        $depth = 0;
        if ($parentUuid !== null && str_starts_with($parentUuid, self::PREFIX)) {
            $parent = $this->decode($request, $parentUuid, $account);
            if ($parent['shared'] !== $shared || $parent['depth'] >= 2) {
                $this->invalid();
            }
            $data['direction_type'] = $parent['data']['direction_type'];
            $data['group_type'] = $parent['data']['group_type'];
            $depth = $parent['depth'] + 1;
        } else {
            $validated = $this->validated($request, $data, $account, $shared);
            $parentId = $validated['parent_id'] ?? null;
            if ($parentId !== null) {
                $parent = Category::findOrFail($parentId);
                $depth = 1;
                for ($cursor = $parent; $cursor->parent_id !== null; $cursor = $cursor->parent) {
                    $depth++;
                }
                if ($depth > 2) {
                    $this->invalid();
                }
            }
            $data = [...$data, ...collect($validated)->except(['parent_id'])->all()];
        }
        $draft = ['actor_id' => $request->user()->id, 'account_id' => $account->id, 'shared' => $shared,
            'uuid' => (string) Str::uuid(), 'depth' => $depth, 'expires_at' => now()->addDay()->timestamp, 'data' => $data];
        $token = self::PREFIX.Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR));

        return [...$data, 'uuid' => $token, 'owner_user_id' => $shared ? $account->user_id : $request->user()->id];
    }

    public function virtualCategory(Request $request, string $token, Account $account): Category
    {
        $draft = $this->decode($request, $token, $account);

        return (new Category([...$draft['data'], 'user_id' => $draft['shared'] ? $account->user_id : $request->user()->id, 'account_id' => $draft['shared'] ? $account->id : null]))->forceFill(['uuid' => $token]);
    }

    /** @param array<string, string> $map */
    public function materialize(Request $request, string $token, array &$map, int $depth = 0): Category
    {
        if ($depth > 2) {
            $this->invalid();
        }
        $account = $this->account($request, (string) $request->input('account_uuid'), $request->input('account_id'));
        $draft = $this->decode($request, $token, $account);
        $catalog = $draft['shared'] ? Account::query()->whereKey($account->id) : $request->user()->newQuery()->whereKey($request->user()->id);
        $catalog->lockForUpdate()->firstOrFail();
        $data = $draft['data'];
        $parentToken = $data['parent_uuid'] ?? null;
        if ($parentToken !== null && str_starts_with($parentToken, self::PREFIX)) {
            $data['parent_uuid'] = $this->materialize($request, $parentToken, $map, $depth + 1)->uuid;
        }
        $existing = Category::query()->where('uuid', $draft['uuid'])->lockForUpdate()->first();
        if ($existing !== null) {
            $parentUuid = $existing->parent?->uuid;
            if ($existing->user_id !== ($draft['shared'] ? $account->user_id : $request->user()->id)
                || $existing->account_id !== ($draft['shared'] ? $account->id : null)
                || $existing->name !== $data['name'] || $parentUuid !== ($data['parent_uuid'] ?? null)
                || ! $existing->is_active || $existing->is_selectable !== (bool) $draft['data']['is_selectable']) {
                $this->invalid();
            }
            $map[$token] = $existing->uuid;

            return $existing;
        }
        $validated = $this->validated($request, $data, $account, $draft['shared']);
        if (($validated['parent_id'] ?? null) !== null) {
            $parent = Category::query()->whereKey($validated['parent_id'])->lockForUpdate()->firstOrFail();
        }
        $nextSortOrder = (int) Category::query()->where('user_id', $draft['shared'] ? $account->user_id : $request->user()->id)
            ->where('account_id', $draft['shared'] ? $account->id : null)->where('parent_id', $validated['parent_id'] ?? null)->max('sort_order') + 1;
        $category = new Category([...$validated, 'user_id' => $draft['shared'] ? $account->user_id : $request->user()->id,
            'account_id' => $draft['shared'] ? $account->id : null, 'name_is_custom' => true, 'sort_order' => $nextSortOrder]);
        $category->forceFill(['uuid' => $draft['uuid']])->save();
        if (! $draft['shared'] && $category->parent_id !== null && Category::query()->where('parent_id', $category->parent_id)->count() === 1) {
            app(CategoryBudgetTransferService::class)->transfer(Category::findOrFail($category->parent_id), $category);
        }
        $map[$token] = $category->uuid;

        return $category;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $data, Account $account, bool $shared): array
    {
        $form = $shared ? new StoreSharedCategoryRequest : new StoreCategoryRequest;
        Request::createFrom(Request::create('/', 'POST', $data), $form);
        $route = clone $request->route();
        $route->setParameter('account', $account);
        $form->setUserResolver(fn () => $request->user());
        $form->setRouteResolver(fn () => $route);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form->validated();
    }

    private function account(Request $request, string $uuid, mixed $id = null): Account
    {
        $query = app(AccessibleAccountsQuery::class)->editable($request->user());
        $account = $uuid !== '' ? $query->where('accounts.uuid', $uuid)->first() : $query->where('accounts.id', is_numeric($id) ? (int) $id : null)->first();
        if ($account === null) {
            $this->invalid();
        }

        return $account;
    }

    /** @return array<string, mixed> */
    private function decode(Request $request, string $token, Account $account): array
    {
        try {
            $draft = json_decode(Crypt::decryptString(substr($token, strlen(self::PREFIX))), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->invalid();
        }
        if (($draft['actor_id'] ?? null) !== $request->user()->id || ($draft['account_id'] ?? null) !== $account->id || ($draft['expires_at'] ?? 0) <= now()->timestamp) {
            $this->invalid();
        }
        if ($draft['shared'] && ! app(SharedAccountCategoryTaxonomyService::class)->isSharedAccount($account)) {
            $this->invalid();
        }

        return $draft;
    }

    /** @return array{transfers: array<int, array<string, mixed>>, confirmation: string} */
    public function effects(Request $request, string $token, bool $lock = false): array
    {
        $account = $this->account($request, (string) $request->input('account_uuid'), $request->input('account_id'));
        $leaf = $this->decode($request, $token, $account);
        if ($lock) {
            ($leaf['shared'] ? Account::query()->whereKey($account->id) : $request->user()->newQuery()->whereKey($request->user()->id))->lockForUpdate()->firstOrFail();
        }
        $cursor = $leaf;
        $transfers = [];
        $snapshot = [];
        for ($depth = 0; $depth <= 2; $depth++) {
            if (Category::query()->where('uuid', $cursor['uuid'])->exists()) {
                break;
            }
            $parentUuid = $cursor['data']['parent_uuid'] ?? null;
            if ($parentUuid === null || $cursor['shared']) {
                break;
            }
            if (str_starts_with($parentUuid, self::PREFIX)) {
                $cursor = $this->decode($request, $parentUuid, $account);

                continue;
            }
            $parent = Category::query()->ownedBy($request->user()->id)->where('uuid', $parentUuid)->when($lock, fn ($query) => $query->lockForUpdate())->first();
            if ($parent === null) {
                $this->invalid();
            }
            if (! $parent->children()->exists()) {
                $budgets = Budget::query()->where('category_id', $parent->id)->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
                $snapshot = $budgets->map(fn (Budget $budget): array => $budget->getRawOriginal())->all();
                $transfers = $budgets->map(fn (Budget $budget): array => [
                    'source' => $parent->name, 'destination' => $leaf['data']['name'], 'amount' => (string) $budget->amount,
                    'year' => $budget->year, 'month' => $budget->month, 'budget_type' => $budget->budget_type,
                    'currency' => $request->user()->base_currency_code ?? 'EUR',
                ])->all();
            }
            break;
        }
        $confirmation = hash_hmac('sha256', json_encode([$request->user()->id, $account->id, $token, $snapshot, $transfers], JSON_THROW_ON_ERROR), (string) config('app.key'));

        return ['transfers' => $transfers, 'confirmation' => $confirmation];
    }

    public function confirmEffects(Request $request, string $token): void
    {
        $effects = $this->effects($request, $token, true);
        $confirmation = $request->input('inline_budget_confirmation');
        if (($effects['transfers'] !== [] || $confirmation !== null) && (! is_string($confirmation) || ! hash_equals($effects['confirmation'], $confirmation))) {
            throw ValidationException::withMessages(['category_uuid' => 'I budget interessati richiedono una nuova conferma. Invia nuovamente il form per controllare origine, destinazione e importi aggiornati.']);
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['category_uuid' => 'La categoria provvisoria non è più disponibile per questo conto. Selezionala nuovamente.']);
    }
}
