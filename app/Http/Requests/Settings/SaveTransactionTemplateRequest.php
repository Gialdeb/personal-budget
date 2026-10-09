<?php

namespace App\Http\Requests\Settings;

use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTransactionTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9-]*$/'],
            'color' => ['required', 'string', 'regex:/^(primary|sky|violet|emerald|rose|amber|#[0-9a-fA-F]{6})$/'],
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'account_uuid' => ['nullable', 'uuid', Rule::exists(Account::class, 'uuid')->where('user_id', $this->user()->id)->where('is_active', true)],
            'category_uuid' => ['nullable', 'uuid', Rule::exists(Category::class, 'uuid')->where('user_id', $this->user()->id)->where('is_active', true)->where('is_selectable', true)],
            'tracked_item_uuid' => ['nullable', 'uuid', Rule::exists(TrackedItem::class, 'uuid')->where('user_id', $this->user()->id)->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'default_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'shared_enabled' => ['required', 'boolean'],
            'shared_counterparty_uuid' => ['nullable', Rule::requiredIf($this->boolean('shared_enabled')), 'uuid', Rule::exists(TrackedItem::class, 'uuid')->where('user_id', $this->user()->id)->where('is_active', true)->where('is_shared_expense_counterpart', true)],
            'shared_payer' => ['required', Rule::in(['self', 'counterparty'])],
            'shared_split_mode' => ['required', Rule::in(['equal', 'percentage'])],
            'my_share_basis_points' => ['required', 'integer', 'between:0,10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function templateData(): array
    {
        $data = $this->validated();
        $data['my_share_basis_points'] = $data['shared_split_mode'] === 'equal' ? 5000 : $data['my_share_basis_points'];
        $data['reference_snapshots'] = [];

        foreach ([
            'account' => Account::class,
            'category' => Category::class,
            'tracked_item' => TrackedItem::class,
            'shared_counterparty' => TrackedItem::class,
        ] as $key => $model) {
            $record = $this->referenceRecord($key, $model);
            $data[$key.'_id'] = $record?->id;
            $data['reference_snapshots'][$key] = $record?->name;
            unset($data[$key.'_uuid']);
        }

        if (! $data['shared_enabled']) {
            $data['shared_counterparty_id'] = null;
        }

        return $data;
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('category_uuid')) {
                return;
            }
            $category = $this->referenceRecord('category', Category::class);
            $account = $this->referenceRecord('account', Account::class);
            $reference = $this->referenceRecord('tracked_item', TrackedItem::class);
            if (! in_array($category->direction_type?->value, [$this->input('direction'), 'mixed'], true) || ($category->account_id && $category->account_id !== $account?->id)) {
                $validator->errors()->add('category_uuid', __('ui.the_category_is_incompatible_with_the_selected_direction_or_acco_8f4e803e'));
            }
            if ($reference) {
                $reference->loadMissing('compatibleCategories');
                $typeKey = $category->group_type?->value ?: $this->input('direction');
                $groups = $reference->settings['transaction_group_keys'] ?? [];
                $contextIds = $account
                    ? app(OperationalTransactionCategoryResolver::class)->categoryContextIdsForAccount($account, $category->id)
                    : [$category->id, $category->parent_id];
                if (($groups && ! in_array($typeKey, $groups, true)) || ($reference->compatibleCategories->isNotEmpty() && $reference->compatibleCategories->pluck('id')->intersect($contextIds)->isEmpty())) {
                    $validator->errors()->add('tracked_item_uuid', __('ui.the_reference_is_incompatible_with_the_selected_category_570a75ad'));
                }
            }
        }];
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel|null
     */
    protected function referenceRecord(string $key, string $model): ?Model
    {
        $uuid = $this->input($key.'_uuid');

        return is_string($uuid) && $uuid !== ''
            ? $model::query()->where('uuid', $uuid)->where('user_id', $this->user()->id)->first()
            : null;
    }
}
