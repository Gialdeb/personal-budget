<?php

namespace Database\Seeders;
use App\Models\Account;
use App\Models\AccountOpeningBalance;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeAccountOpeningBalanceSeeder extends Seeder
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

        $rows = [
            ['account' => 'Conto Intesa Personale', 'date' => '2025-01-01', 'amount' => 3200.00],
            ['account' => 'Carta Revolut', 'date' => '2025-01-01', 'amount' => 450.00],
            ['account' => 'Cassa Casa 1', 'date' => '2025-01-01', 'amount' => 250.00],
        ];

        foreach ($rows as $row) {
            $account = $accounts[$row['account']] ?? null;

            if (! $account) {
                continue;
            }

            AccountOpeningBalance::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'balance_date' => $row['date'],
                ],
                [
                    'amount' => $row['amount'],
                    'notes' => 'Saldo iniziale seed 2025',
                    'created_by' => $user->id,
                ]
            );
        }
    }
}
