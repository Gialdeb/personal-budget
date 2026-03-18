<?php

namespace Database\Seeders;

use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\ScheduledEntry;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeScheduledEntrySeeder extends Seeder
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
                'title' => 'Assicurazione auto',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Personale',
                'category' => 'Auto',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::EXPENSE,
                'amount' => 420.00,
                'scheduled_date' => '2025-09-10',
                'status' => ScheduledEntryStatusEnum::PLANNED,
            ],
            [
                'title' => 'TARI Casa 1',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Casa 1',
                'category' => 'TARI',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::EXPENSE,
                'amount' => 260.00,
                'scheduled_date' => '2025-11-15',
                'status' => ScheduledEntryStatusEnum::PLANNED,
            ],
            [
                'title' => 'Visita medica specialistica',
                'account' => 'Carta Revolut',
                'scope' => 'Personale',
                'category' => 'Salute',
                'merchant' => 'Farmacia Centrale',
                'direction' => TransactionDirectionEnum::EXPENSE,
                'amount' => 120.00,
                'scheduled_date' => '2025-10-07',
                'status' => ScheduledEntryStatusEnum::PLANNED,
            ],
            [
                'title' => 'Entrata extra freelance',
                'account' => 'Conto Intesa Personale',
                'scope' => 'Personale',
                'category' => 'Altre entrate',
                'merchant' => null,
                'direction' => TransactionDirectionEnum::INCOME,
                'amount' => 600.00,
                'scheduled_date' => '2025-06-25',
                'status' => ScheduledEntryStatusEnum::PLANNED,
            ],
        ];

        foreach ($rows as $row) {
            ScheduledEntry::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $row['title'],
                    'scheduled_date' => $row['scheduled_date'],
                ],
                [
                    'account_id' => $accounts[$row['account']]->id ?? null,
                    'scope_id' => $scopes[$row['scope']]->id ?? null,
                    'category_id' => $categories[$row['category']]->id ?? null,
                    'merchant_id' => $row['merchant'] ? ($merchants[$row['merchant']]->id ?? null) : null,
                    'description' => $row['title'],
                    'direction' => $row['direction'],
                    'expected_amount' => $row['amount'],
                    'currency' => 'EUR',
                    'status' => $row['status'],
                    'notes' => 'Seed scadenza singola 2025',
                ]
            );
        }
    }
}
