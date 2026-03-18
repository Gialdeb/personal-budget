<?php

namespace Database\Seeders;

use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeRecurringEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $accounts = Account::where('user_id', $user->id)->get()->keyBy('name');
        $scopes = Scope::where('user_id', $user->id)->get()->keyBy('name');
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');
        $merchants = Merchant::where('user_id', $user->id)->get()->keyBy('name');

        $rows = [
            [
                'title' => 'Bolletta luce Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'Luce',
                'merchant' => 'Enel Energia',
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 95.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2024-01-01',
                'end_date' => '2025-12-31',
                'due_day' => 16,
            ],
            [
                'title' => 'Canone internet Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'Internet',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 29.90,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2024-01-01',
                'end_date' => '2025-12-31',
                'due_day' => 19,
            ],
            [
                'title' => 'Bolletta acqua Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'Acqua',
                'merchant' => 'GORI',
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 46.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::QUARTERLY,
                'start_date' => '2024-02-01',
                'end_date' => '2025-12-31',
                'due_day' => 14,
            ],
            [
                'title' => 'Quota condominio Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'Condominio',
                'merchant' => 'Condominio Via Roma',
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 112.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::QUARTERLY,
                'start_date' => '2024-01-01',
                'end_date' => '2025-12-31',
                'due_day' => 22,
            ],
            [
                'title' => 'Spesa alimentari personale',
                'account' => 'Carta Revolut',
                'scope' => 'Personale',
                'category' => 'Alimentari',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 390.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2024-01-01',
                'end_date' => '2025-12-31',
                'due_day' => 9,
            ],
            [
                'title' => 'Stipendio mensile',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Personale',
                'category' => 'Stipendio',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::INCOME,
                'expected_amount' => 1900.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2024-01-01',
                'end_date' => '2025-12-31',
                'due_day' => 5,
            ],
            [
                'title' => 'Spese cane',
                'account' => 'Carta Revolut',
                'scope' => 'Personale',
                'category' => 'Cane',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 58.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'due_day' => 21,
            ],
        ];

        foreach ($rows as $row) {
            RecurringEntry::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $row['title'],
                ],
                [
                    'account_id' => $accounts[$row['account']]->id ?? null,
                    'scope_id' => $scopes[$row['scope']]->id ?? null,
                    'category_id' => $categories[$row['category']]->id ?? null,
                    'merchant_id' => $row['merchant'] ? ($merchants[$row['merchant']]->id ?? null) : null,
                    'description' => $row['title'],
                    'direction' => $row['direction'],
                    'expected_amount' => $row['expected_amount'],
                    'currency' => 'EUR',
                    'recurrence_type' => $row['recurrence_type'],
                    'recurrence_interval' => 1,
                    'recurrence_rule' => null,
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'due_day' => $row['due_day'],
                    'auto_generate_occurrences' => true,
                    'auto_create_transaction' => false,
                    'is_active' => true,
                    'notes' => 'Seed ricorrenza 2024-2025',
                ]
            );
        }
    }
}
