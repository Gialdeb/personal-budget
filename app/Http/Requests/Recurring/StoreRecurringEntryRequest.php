<?php

namespace App\Http\Requests\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Scope;
use App\Models\TrackedItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'account_id' => ['required', 'integer'],
            'account_uuid' => ['nullable', 'uuid'],
            'scope_id' => ['nullable', 'integer'],
            'scope_uuid' => ['nullable', 'uuid'],
            'category_id' => ['required', 'integer'],
            'category_uuid' => ['nullable', 'uuid'],
            'tracked_item_id' => ['nullable', 'integer'],
            'tracked_item_uuid' => ['nullable', 'uuid'],
            'merchant_id' => ['nullable', 'integer'],
            'merchant_uuid' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'direction' => ['required', Rule::in([
                TransactionDirectionEnum::INCOME->value,
                TransactionDirectionEnum::EXPENSE->value,
            ])],
            'currency' => ['nullable', 'string', 'size:3'],
            'entry_type' => ['required', Rule::in(RecurringEntryTypeEnum::values())],
            'status' => ['nullable', Rule::in(RecurringEntryStatusEnum::values())],
            'recurrence_type' => ['required', Rule::in(RecurringEntryRecurrenceTypeEnum::values())],
            'recurrence_interval' => ['required', 'integer', 'min:1'],
            'recurrence_rule' => ['nullable', 'array'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_mode' => ['nullable', Rule::in(RecurringEndModeEnum::values())],
            'occurrences_limit' => ['nullable', 'integer', 'min:1'],
            'expected_amount' => ['nullable', 'numeric', 'gt:0'],
            'total_amount' => ['nullable', 'numeric', 'gt:0'],
            'installments_count' => ['nullable', 'integer', 'min:1'],
            'auto_generate_occurrences' => ['sometimes', 'boolean'],
            'auto_create_transaction' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $userId = $this->user()?->id;

        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->input('title')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'direction' => $this->filled('direction') ? (string) $this->input('direction') : null,
            'currency' => $this->filled('currency') ? strtoupper(trim((string) $this->input('currency'))) : null,
            'entry_type' => $this->filled('entry_type') ? (string) $this->input('entry_type') : null,
            'status' => $this->filled('status') ? (string) $this->input('status') : RecurringEntryStatusEnum::ACTIVE->value,
            'recurrence_type' => $this->filled('recurrence_type') ? (string) $this->input('recurrence_type') : null,
            'recurrence_interval' => $this->filled('recurrence_interval') ? (int) $this->input('recurrence_interval') : 1,
            'account_id' => $this->resolveOwnedId(
                Account::class,
                'account_id',
                'account_uuid',
                $userId
            ),
            'scope_id' => $this->resolveOwnedId(
                Scope::class,
                'scope_id',
                'scope_uuid',
                $userId,
                'user_id'
            ),
            'category_id' => $this->resolveOwnedId(
                Category::class,
                'category_id',
                'category_uuid',
                $userId
            ),
            'tracked_item_id' => $this->resolveOwnedId(
                TrackedItem::class,
                'tracked_item_id',
                'tracked_item_uuid',
                $userId
            ),
            'merchant_id' => $this->resolveOwnedId(
                Merchant::class,
                'merchant_id',
                'merchant_uuid',
                $userId,
                'user_id'
            ),
            'occurrences_limit' => $this->filled('occurrences_limit') ? (int) $this->input('occurrences_limit') : null,
            'expected_amount' => $this->filled('expected_amount') ? (float) $this->input('expected_amount') : null,
            'total_amount' => $this->filled('total_amount') ? (float) $this->input('total_amount') : null,
            'installments_count' => $this->filled('installments_count') ? (int) $this->input('installments_count') : null,
            'auto_generate_occurrences' => $this->has('auto_generate_occurrences') ? $this->boolean('auto_generate_occurrences') : true,
            'auto_create_transaction' => $this->has('auto_create_transaction') ? $this->boolean('auto_create_transaction') : false,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'end_mode' => $this->filled('end_mode') ? (string) $this->input('end_mode') : RecurringEndModeEnum::NEVER->value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => __('transactions.validation.recurring_end_date_after_start_date'),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveOwnedId(
        string $modelClass,
        string $idField,
        string $uuidField,
        ?int $userId,
        string $ownerColumn = 'user_id',
    ): ?int {
        if ($this->filled($idField) && is_numeric($this->input($idField))) {
            return (int) $this->input($idField);
        }

        if (! $this->filled($uuidField) || $userId === null) {
            return null;
        }

        return $modelClass::query()
            ->where($ownerColumn, $userId)
            ->where('uuid', (string) $this->input($uuidField))
            ->value('id');
    }
}
