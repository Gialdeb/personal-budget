<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'expected_amount' => 90.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2025-01-01',
                'due_day' => 15,
            ],
            [
                'title' => 'Bolletta acqua Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'Acqua',
                'merchant' => 'GORI',
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 45.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2025-01-01',
                'due_day' => 20,
            ],
            [
                'title' => 'Quota condominio Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'Condominio',
                'merchant' => 'Condominio Via Roma',
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 110.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2025-01-01',
                'due_day' => 22,
            ],
            [
                'title' => 'Budget alimentari personale',
                'account' => 'Carta Revolut',
                'scope' => 'Personale',
                'category' => 'Alimentari',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::EXPENSE,
                'expected_amount' => 350.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2025-01-01',
                'due_day' => 8,
            ],
            [
                'title' => 'Stipendio mensile',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Personale',
                'category' => 'Stipendio',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::INCOME,
                'expected_amount' => 1800.00,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
                'start_date' => '2025-01-01',
                'due_day' => 5,
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
                    'end_date' => '2025-12-31',
                    'due_day' => $row['due_day'],
                    'auto_generate_occurrences' => true,
                    'auto_create_transaction' => false,
                    'is_active' => true,
                    'notes' => 'Seed ricorrenza 2025',
                ]
            );
        }
    }
}
