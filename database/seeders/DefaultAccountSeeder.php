<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultAccountSeeder extends Seeder
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

        $bankType = AccountType::where('code', 'bank')->first();
        $cashType = AccountType::where('code', 'cash')->first();
        $cardType = AccountType::where('code', 'card')->first();

        $intesa = Bank::where('slug', 'intesa-sanpaolo')->first();
        $revolut = Bank::where('slug', 'revolut')->first();

        $personalScope = Scope::where('user_id', $user->id)->where('name', 'Personale')->first();
        $home1Scope = Scope::where('user_id', $user->id)->where('name', 'Casa 1')->first();

        $accounts = [
            [
                'name' => 'Conto Intesa Personale',
                'bank_id' => $intesa?->id,
                'account_type_id' => $bankType?->id,
                'scope_id' => $personalScope?->id,
                'currency' => 'EUR',
                'opening_balance' => 3200.00,
                'current_balance' => 3200.00,
                'is_manual' => false,
            ],
            [
                'name' => 'Carta Revolut',
                'bank_id' => $revolut?->id,
                'account_type_id' => $cardType?->id,
                'scope_id' => $personalScope?->id,
                'currency' => 'EUR',
                'opening_balance' => 450.00,
                'current_balance' => 450.00,
                'is_manual' => false,
            ],
            [
                'name' => 'Cassa Casa 1',
                'bank_id' => null,
                'account_type_id' => $cashType?->id,
                'scope_id' => $home1Scope?->id,
                'currency' => 'EUR',
                'opening_balance' => 250.00,
                'current_balance' => 250.00,
                'is_manual' => true,
            ],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $account['name'],
                ],
                [
                    'bank_id' => $account['bank_id'],
                    'account_type_id' => $account['account_type_id'],
                    'scope_id' => $account['scope_id'],
                    'currency' => $account['currency'],
                    'opening_balance' => $account['opening_balance'],
                    'current_balance' => $account['current_balance'],
                    'is_manual' => $account['is_manual'],
                    'is_active' => true,
                ]
            );
        }
    }
}
