<?php

namespace App\Http\Requests\Transactions;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
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
        $userId = $this->user()->id;
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $daysInMonth = CarbonImmutable::create($routeYear, $routeMonth, 1)->daysInMonth;

        return [
            'transaction_day' => ['required', 'integer', 'between:1,'.$daysInMonth],
            'transaction_date' => ['nullable', 'date'],
            'type_key' => ['required', Rule::in([
                CategoryGroupTypeEnum::INCOME->value,
                CategoryGroupTypeEnum::EXPENSE->value,
                CategoryGroupTypeEnum::BILL->value,
                CategoryGroupTypeEnum::DEBT->value,
                CategoryGroupTypeEnum::SAVING->value,
                CategoryGroupTypeEnum::TAX->value,
                CategoryGroupTypeEnum::INVESTMENT->value,
                CategoryGroupTypeEnum::TRANSFER->value,
            ])],
            'kind' => ['prohibited'],
            'account_uuid' => [
                Rule::requiredIf(fn (): bool => ! $this->filled('account_id')),
                'nullable',
                'uuid',
            ],
            'account_id' => [
                'nullable',
                'integer',
            ],
            'destination_account_uuid' => ['nullable', 'uuid'],
            'destination_account_id' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                ),
                'nullable',
                'integer',
            ],
            'category_uuid' => ['nullable', 'uuid'],
            'category_id' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('type_key') !== CategoryGroupTypeEnum::TRANSFER->value
                ),
                'nullable',
                'integer',
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'tracked_item_uuid' => ['nullable', 'uuid'],
            'tracked_item_id' => [
                'nullable',
                'integer',
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_day.required' => 'Il giorno del movimento è obbligatorio.',
            'transaction_day.integer' => 'Il giorno del movimento deve essere numerico.',
            'transaction_day.between' => 'Il giorno selezionato non è valido per il mese visualizzato.',
            'transaction_date.date' => 'La data movimento deve essere valida.',
            'type_key.required' => 'Seleziona il tipo della registrazione.',
            'type_key.in' => 'Il tipo selezionato non è valido.',
            'kind.prohibited' => 'Il tipo operativo della transazione non può essere impostato manualmente.',
            'account_uuid.required' => 'Seleziona un conto.',
            'destination_account_uuid.required' => 'Seleziona il conto di destinazione.',
            'category_uuid.required' => 'Seleziona una categoria.',
            'amount.required' => "L'importo è obbligatorio.",
            'amount.numeric' => "L'importo deve essere numerico.",
            'amount.gt' => "L'importo deve essere maggiore di zero.",
            'notes.max' => 'Le note sono troppo lunghe.',
            'description.max' => 'Il dettaglio è troppo lungo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $transactionDay = null;

        if ($this->filled('transaction_day')) {
            $transactionDay = (int) $this->input('transaction_day');
        } elseif ($this->filled('transaction_date')) {
            try {
                $transactionDay = CarbonImmutable::parse((string) $this->input('transaction_date'))->day;
            } catch (\Throwable) {
                $transactionDay = null;
            }
        }

        $accountUuid = $this->filled('account_uuid') ? (string) $this->input('account_uuid') : null;
        $destinationAccountUuid = $this->filled('destination_account_uuid')
            ? (string) $this->input('destination_account_uuid')
            : null;
        $categoryUuid = $this->filled('category_uuid') ? (string) $this->input('category_uuid') : null;
        $trackedItemUuid = $this->filled('tracked_item_uuid') ? (string) $this->input('tracked_item_uuid') : null;

        $this->merge([
            'transaction_day' => $transactionDay,
            'transaction_date' => $transactionDay !== null
                ? sprintf('%04d-%02d-%02d', $routeYear, $routeMonth, $transactionDay)
                : null,
            'account_uuid' => $accountUuid,
            'account_id' => $this->filled('account_id')
                ? (int) $this->input('account_id')
                : ($accountUuid === null
                    ? null
                    : Account::query()->where('uuid', $accountUuid)->value('id')),
            'destination_account_uuid' => $destinationAccountUuid,
            'destination_account_id' => $this->filled('destination_account_id')
                ? (int) $this->input('destination_account_id')
                : ($destinationAccountUuid !== null
                    ? Account::query()->where('uuid', $destinationAccountUuid)->value('id')
                    : null),
            'category_uuid' => $categoryUuid,
            'category_id' => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                ? null
                : ($this->filled('category_id')
                    ? (int) $this->input('category_id')
                    : ($categoryUuid !== null ? Category::query()->where('uuid', $categoryUuid)->value('id') : null)),
            'amount' => $this->filled('amount') ? (float) $this->input('amount') : null,
            'tracked_item_uuid' => $trackedItemUuid,
            'tracked_item_id' => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                ? null
                : ($this->filled('tracked_item_id')
                    ? (int) $this->input('tracked_item_id')
                    : ($trackedItemUuid !== null ? TrackedItem::query()->where('uuid', $trackedItemUuid)->value('id') : null)),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'type_key' => $this->filled('type_key') ? (string) $this->input('type_key') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            // Skip advanced validation if basic field validation already failed
            if ($validator->errors()->has(['transaction_day', 'type_key', 'amount'])) {
                return;
            }

            $date = (string) $this->input('transaction_date');
            $routeYear = (int) $this->route('year');
            $routeMonth = (int) $this->route('month');

            // Date and year validation
            if ($date && ! $validator->errors()->has('transaction_date')) {
                app(UserYearService::class)->ensureDateYearIsOpen(
                    $user,
                    $date,
                    'transaction_date'
                );

                try {
                    $parsedDate = CarbonImmutable::parse($date);

                    if ($parsedDate->year !== $routeYear || $parsedDate->month !== $routeMonth) {
                        $validator->errors()->add(
                            'transaction_date',
                            'La data movimento deve restare nel mese visualizzato.'
                        );
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('transaction_date', 'La data movimento deve essere valida.');
                }
            }

            if ($this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value) {
                if (
                    $this->filled('account_id')
                    && $this->filled('destination_account_id')
                    && $this->integer('account_id') === $this->integer('destination_account_id')
                ) {
                    $validator->errors()->add(
                        'destination_account_uuid',
                        'Il conto di destinazione deve essere diverso dal conto sorgente.'
                    );
                }

                if ($this->filled('account_uuid') && ! $this->filled('account_id')) {
                    $validator->errors()->add('account_uuid', 'Il conto selezionato non è disponibile.');
                }

                if ($this->filled('destination_account_uuid') && ! $this->filled('destination_account_id')) {
                    $validator->errors()->add(
                        'destination_account_uuid',
                        'Il conto di destinazione selezionato non è disponibile.'
                    );
                }

                return;
            }

            if ($this->filled('account_uuid') && ! $this->filled('account_id')) {
                $validator->errors()->add('account_uuid', 'Il conto selezionato non è disponibile.');
            }

            $category = Category::query()
                ->ownedBy($user->id)
                ->find($this->integer('category_id'));

            if (! $category instanceof Category) {
                if ($this->filled('category_uuid')) {
                    $validator->errors()->add('category_uuid', 'La categoria selezionata non è disponibile.');
                }

                return;
            }

            if ($category->group_type === CategoryGroupTypeEnum::TRANSFER) {
                $validator->errors()->add(
                    'category_uuid',
                    'Per i giroconti usa il tipo Giroconto, non una categoria standard.'
                );
            }

            if (! $category->is_selectable) {
                $validator->errors()->add(
                    'category_uuid',
                    'Seleziona una categoria foglia operativa.'
                );
            }

            if (
                $category->group_type === null
                && $category->direction_type?->value !== null
                && $category->direction_type->value !== $this->resolvedDirectionFromTypeKey()
            ) {
                $validator->errors()->add(
                    'category_uuid',
                    'La categoria selezionata non è coerente con il tipo della registrazione.'
                );
            }

            $categoryTypeKey = $category->group_type?->value
                ?? ($category->direction_type?->value === CategoryDirectionTypeEnum::INCOME->value
                    ? CategoryGroupTypeEnum::INCOME->value
                    : CategoryGroupTypeEnum::EXPENSE->value);

            if ($categoryTypeKey !== $this->input('type_key')) {
                $validator->errors()->add(
                    'category_uuid',
                    'La categoria selezionata non appartiene al tipo scelto.'
                );
            }

            if (! $this->filled('tracked_item_id')) {
                return;
            }

            $trackedItem = TrackedItem::query()
                ->ownedBy($user->id)
                ->with('compatibleCategories:id,parent_id,user_id')
                ->find($this->integer('tracked_item_id'));

            if (! $trackedItem instanceof TrackedItem) {
                if ($this->filled('tracked_item_uuid')) {
                    $validator->errors()->add(
                        'tracked_item_uuid',
                        "L'elemento tracciato selezionato non è disponibile."
                    );
                } elseif ($this->filled('tracked_item_id')) {
                    $validator->errors()->add(
                        'tracked_item_id',
                        "L'elemento tracciato selezionato non è disponibile."
                    );
                }

                return;
            }

            $settings = is_array($trackedItem->settings) ? $trackedItem->settings : [];
            $groupKeys = collect($settings['transaction_group_keys'] ?? [])
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->values()
                ->all();
            $categoryIds = $trackedItem->compatibleCategories
                ->pluck('id')
                ->map(fn ($value): int => (int) $value)
                ->values()
                ->all();

            if ($groupKeys !== [] && ! in_array((string) $this->input('type_key'), $groupKeys, true)) {
                $validator->errors()->add(
                    $this->filled('tracked_item_uuid') ? 'tracked_item_uuid' : 'tracked_item_id',
                    "L'elemento da tracciare non appartiene al tipo selezionato."
                );
            }

            if (
                $categoryIds !== []
                && count(array_intersect(
                    $categoryIds,
                    $this->categoryContextIds($user->id, (int) $this->input('category_id'))
                )) === 0
            ) {
                $validator->errors()->add(
                    $this->filled('tracked_item_uuid') ? 'tracked_item_uuid' : 'tracked_item_id',
                    "L'elemento da tracciare non appartiene alla categoria selezionata."
                );
            }
        });
    }

    public function resolvedDirectionFromTypeKey(): string
    {
        return match ($this->input('type_key')) {
            CategoryGroupTypeEnum::INCOME->value => CategoryDirectionTypeEnum::INCOME->value,
            CategoryGroupTypeEnum::TRANSFER->value => CategoryDirectionTypeEnum::TRANSFER->value,
            default => CategoryDirectionTypeEnum::EXPENSE->value,
        };
    }

    /**
     * @return array<int, int>
     */
    protected function categoryContextIds(int $userId, int $categoryId): array
    {
        $categories = Category::query()
            ->ownedBy($userId)
            ->get(['id', 'parent_id'])
            ->keyBy('id');

        $contextIds = [];
        $currentCategoryId = $categoryId;
        $visited = [];

        while ($currentCategoryId > 0 && $categories->has($currentCategoryId)) {
            // Prevent infinite loops by checking if we've already visited this ID
            if (in_array($currentCategoryId, $visited, true)) {
                break;
            }

            $visited[] = $currentCategoryId;
            $contextIds[] = $currentCategoryId;
            $currentCategoryId = (int) ($categories[$currentCategoryId]->parent_id ?? 0);
        }

        return array_values(array_unique($contextIds));
    }
}
