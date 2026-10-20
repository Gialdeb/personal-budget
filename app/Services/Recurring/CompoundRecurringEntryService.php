<?php

namespace App\Services\Recurring;

use App\Models\Category;
use App\Models\CompoundRecurringComponent;
use App\Models\CompoundRecurringEntry;
use App\Models\RecurringEntry;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompoundRecurringEntryService
{
    public function __construct(private RecurringEntryManagementService $entries, private AccessibleAccountsQuery $accounts) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): CompoundRecurringEntry
    {
        return DB::transaction(function () use ($actor, $data): CompoundRecurringEntry {
            $account = $this->accounts->editable($actor)->where('accounts.uuid', $data['account_uuid'])->first();
            if (! $account || ! $account->currency) {
                throw ValidationException::withMessages(['account_uuid' => __('compound_recurring.account_unavailable')]);
            }
            $category = Category::query()->where('uuid', $data['category_uuid'])->first();
            if (! $category) {
                throw ValidationException::withMessages(['category_uuid' => __('compound_recurring.category_unavailable')]);
            }
            $compound = CompoundRecurringEntry::query()->create([
                'name' => trim($data['name']), 'user_id' => $account->user_id, 'account_id' => $account->id,
                'category_id' => $category->id, 'currency' => $account->currency, 'direction' => $data['direction'],
            ]);
            $this->add($actor, $compound, $data['components'], $data['reminder_days_before'] ?? []);

            return $compound;
        });
    }

    public function archive(User $actor, CompoundRecurringEntry $compound, CompoundRecurringComponent $component): void
    {
        DB::transaction(function () use ($actor, $compound, $component): void {
            $compound = CompoundRecurringEntry::query()->lockForUpdate()->findOrFail($compound->id);
            abort_unless($this->accounts->canEditAccountId($actor, $compound->account_id), 403);
            $component = $compound->components()->whereKey($component->id)->lockForUpdate()->firstOrFail();
            if ($component->archived_at !== null) {
                return;
            }
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($component->recurring_entry_id);
            $entry = $this->entries->cancel($entry);
            $entry->forceFill(['auto_generate_occurrences' => false])->save();
            $component->forceFill(['archived_at' => now()])->save();
        });
    }

    /**
     * @param  array<int, array{name: string, date: string, amount: string|float}>  $components
     * @param  array<int, int>  $reminderDaysBefore
     */
    public function add(User $actor, CompoundRecurringEntry $compound, array $components, array $reminderDaysBefore = []): void
    {
        DB::transaction(function () use ($actor, $compound, $components, $reminderDaysBefore): void {
            $compound = CompoundRecurringEntry::query()->lockForUpdate()->findOrFail($compound->id);
            abort_unless($this->accounts->canEditAccountId($actor, $compound->account_id), 403);
            $existingNames = $compound->components()->pluck('name')->map(fn (string $name): string => mb_strtolower(trim($name)))->all();
            foreach ($components as $index => $component) {
                $name = trim($component['name']);
                if ($name === '' || in_array(mb_strtolower($name), $existingNames, true)) {
                    throw ValidationException::withMessages(['components.'.$index.'.name' => __('compound_recurring.duplicate_component')]);
                }
                $existingNames[] = mb_strtolower($name);
                $date = CarbonImmutable::parse($component['date']);
                try {
                    $this->entries->store($actor, [
                        'title' => $compound->name.' · '.$name, 'account_id' => $compound->account_id,
                        'category_id' => $compound->category_id, 'direction' => $compound->direction,
                        'currency' => $compound->currency, 'entry_type' => 'recurring', 'is_amount_variable' => true,
                        'expected_amount' => $component['amount'], 'start_date' => $date->toDateString(),
                        'recurrence_type' => 'yearly', 'recurrence_interval' => 1,
                        'recurrence_rule' => ['mode' => 'month_day', 'month' => $date->month, 'day' => $date->day],
                        'reminder_days_before' => $reminderDaysBefore,
                        'end_mode' => 'never', 'status' => 'active', 'is_active' => true,
                        'auto_generate_occurrences' => true, 'auto_create_transaction' => false,
                    ], fn ($entry) => $compound->components()->create(['name' => $name, 'recurring_entry_id' => $entry->id]));
                } catch (ValidationException $exception) {
                    $fields = [
                        'start_date' => 'components.'.$index.'.date',
                        'first_billing_date' => 'components.'.$index.'.date',
                        'expected_amount' => 'components.'.$index.'.amount',
                        'title' => 'components.'.$index.'.name',
                        'account_id' => 'account_uuid', 'category_id' => 'category_uuid',
                    ];
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $target = $fields[$field] ?? $field;
                        $errors[$target] = array_merge($errors[$target] ?? [], $messages);
                    }
                    throw ValidationException::withMessages($errors);
                }
            }
        });
    }
}
