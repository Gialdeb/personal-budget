<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\TrackedItem;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Categories\PendingCategoryService;
use App\Services\TrackedItems\PendingReferenceLogoService;
use App\Services\TrackedItems\PendingTrackedItemService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MaterializePendingTrackedItems
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('categories.inline-effects')) {
            return $next($request);
        }
        if ($request->boolean('defer_category') && $request->routeIs('categories.store', 'shared-categories.store') && $request->isMethod('POST')) {
            abort_unless($request->user() !== null, 403);

            return response()->json(['category' => app(PendingCategoryService::class)->preview($request)]);
        }
        $categoryToken = $request->input('category_uuid');
        $hasPendingCategory = is_string($categoryToken) && str_starts_with($categoryToken, PendingCategoryService::PREFIX);
        if ($hasPendingCategory && $request->boolean('draft') && $request->routeIs('transactions.tracked-items.store', 'recurring-entries.tracked-items.store')) {
            abort_unless($request->user() !== null, 403);
            $input = Validator::make($request->all(), [
                'name' => ['required_without:tracked_item_uuid', 'nullable', 'string', 'max:150'], 'tracked_item_uuid' => ['nullable', 'uuid'],
                'account_uuid' => ['required', 'uuid'], 'type_key' => ['nullable', 'in:income,expense,bill,debt,saving'],
                'direction' => ['nullable', 'in:income,expense'], 'website_url' => ['required_if:logo_source,website', 'nullable', 'string', 'max:2048'], 'logo_identity_uuid' => ['sometimes', 'nullable', 'uuid'],
                'logo_brand_uuid' => ['sometimes', 'nullable', 'uuid'],
                'logo_upload_token' => ['sometimes', 'nullable', 'string', 'max:8192'], 'logo_source' => ['sometimes', 'in:website'],
            ])->validate();
            $account = app(AccessibleAccountsQuery::class)->editable($request->user())->where('accounts.uuid', $input['account_uuid'])->firstOrFail();
            $category = app(PendingCategoryService::class)->virtualCategory($request, $categoryToken, $account);

            return response()->json(['item' => app(PendingTrackedItemService::class)->preview($request->user(), $input, $account, $category)]);
        }
        $fields = collect(['tracked_item_uuid', 'reference_uuid', 'counterparty_uuid', 'shared_expense_counterparty_uuid', 'shared_counterparty_uuid'])
            ->filter(fn (string $field): bool => is_string($request->input($field)) && str_starts_with($request->input($field), PendingTrackedItemService::PREFIX));
        $plainField = $request->routeIs('credits-debts.store', 'credits-debts.update') ? 'reference_uuid' : 'tracked_item_uuid';
        $plainUuid = $request->input($plainField);
        $hasPlainReference = is_string($plainUuid) && Str::isUuid($plainUuid) && $request->routeIs(
            'transactions.store', 'transactions.update', 'transactions.shared-ledger.update',
            'recurring-entries.store', 'recurring-entries.update', 'recurring-entries.reactivate',
            'transaction-templates.store', 'transaction-templates.update', 'credits-debts.store', 'credits-debts.update', 'imports.rows.update-review',
        );
        if (($fields->isEmpty() && ! $hasPlainReference && ! $hasPendingCategory) || str_contains($request->path(), 'preview') || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }
        abort_unless($request->routeIs(
            'transactions.store', 'transactions.update', 'transactions.shared-ledger.update',
            'recurring-entries.store', 'recurring-entries.update', 'recurring-entries.reactivate',
            'transaction-templates.store', 'transaction-templates.update', 'imports.rows.update-review',
            'credits-debts.store', 'credits-debts.update', 'shared-expenses.direct.store',
            'counterparties.payments.store', 'counterparties.payments.update',
        ), 422, 'Questa selezione deve essere confermata nel form di origine.');
        abort_unless($request->user() !== null, 403);
        $previousErrors = $request->hasSession() ? $request->session()->get('errors') : null;
        DB::beginTransaction();
        try {
            $categoryMap = [];
            if ($hasPendingCategory) {
                app(PendingCategoryService::class)->confirmEffects($request, $categoryToken);
                $category = app(PendingCategoryService::class)->materialize($request, $categoryToken, $categoryMap);
                $request->merge(['category_uuid' => $category->uuid, 'category_id' => $category->id]);
            }
            if ($hasPlainReference) {
                $accountQuery = app(AccessibleAccountsQuery::class)->editable($request->user());
                $account = is_string($request->input('account_uuid')) && Str::isUuid($request->input('account_uuid'))
                    ? $accountQuery->where('accounts.uuid', $request->input('account_uuid'))->first()
                    : $accountQuery->where('accounts.id', is_numeric($request->input('account_id')) ? (int) $request->input('account_id') : null)->first();
                $categoryId = is_string($request->input('category_uuid')) && Str::isUuid($request->input('category_uuid'))
                    ? Category::query()->where('uuid', $request->input('category_uuid'))->value('id')
                    : $request->input('category_id');
                $category = $account !== null && is_numeric($categoryId)
                    ? app(OperationalTransactionCategoryResolver::class)->findCategoryForAccount($account, (int) $categoryId) : null;
                $reference = TrackedItem::query()->where('uuid', $plainUuid)->first();
                if ($request->isMethod('POST') && $reference?->identityAlias()->exists()) {
                    $reference = app(TrackedItemIdentityService::class)->canonicalFor($reference);
                    $plainUuid = $reference->uuid;
                }
                if ($account !== null && $category !== null && $reference?->is_active && ($reference->account_id !== null || $reference->user_id === $request->user()->id) && ! $reference->identityAlias()->exists()) {
                    $draft = app(PendingTrackedItemService::class)->preview($request->user(), [
                        'tracked_item_uuid' => $plainUuid,
                        'context' => $request->routeIs('credits-debts.store', 'credits-debts.update') ? 'credit_debt' : null,
                        'type_key' => $request->input('type_key') ?? $category->group_type?->value ?? $request->input('direction'),
                    ], $account, $category);
                    $request->merge([$plainField => $draft['value'], 'category_uuid' => $category->uuid, 'category_id' => $category->id]);
                    $fields->push($plainField);
                }
            }
            $input = $request->all();
            foreach ($fields as $field) {
                if (($field === 'shared_expense_counterparty_uuid' && ! $request->boolean('is_shared_expense'))
                    || ($field === 'shared_counterparty_uuid' && ! $request->boolean('shared_enabled'))
                    || (in_array($field, ['tracked_item_uuid', 'reference_uuid'], true) && (in_array($request->input('type_key'), ['transfer', 'balance_adjustment', 'move'], true) || $request->input('type') === 'transfer'))) {
                    $request->merge([$field => null, substr($field, 0, -5).'_id' => null]);

                    continue;
                }
                $item = app(PendingTrackedItemService::class)->materialize($request->user(), $input[$field], $field, $input, $categoryMap);
                $request->merge([$field => $item->uuid]);
                $idField = substr($field, 0, -5).'_id';
                if ($request->exists($idField)) {
                    if ($request->filled($idField) && (! is_numeric($request->input($idField)) || (int) $request->input($idField) !== $item->id)) {
                        throw ValidationException::withMessages([$field => 'La selezione del riferimento non corrisponde ai dati del form. Selezionalo nuovamente prima di salvare.']);
                    }
                    $request->merge([$idField => $item->id]);
                }
            }
            $response = $next($request);
            if ($response->getStatusCode() >= 400 || ($request->hasSession() && $request->session()->get('errors') !== $previousErrors)) {
                DB::rollBack();
                app(PendingReferenceLogoService::class)->finish(false);
            } else {
                DB::commit();
                app(PendingReferenceLogoService::class)->finish(true);
            }

            return $response;
        } catch (Throwable $exception) {
            DB::rollBack();
            app(PendingReferenceLogoService::class)->finish(false);
            throw $exception;
        }
    }
}
