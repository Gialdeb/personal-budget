<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use App\Models\User;
use App\Models\UserBank;
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

        $paymentAccountType = AccountType::where('code', 'payment_account')->first();
        $cashAccountType = AccountType::where('code', 'cash_account')->first();
        $creditCardType = AccountType::where('code', 'credit_card')->first();

        $intesa = Bank::where('slug', 'intesa-sanpaolo')->first();
        $revolut = Bank::where('slug', 'revolut')->first();
        $intesaUserBank = $intesa === null
            ? null
            : UserBank::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bank_id' => $intesa->id,
                ],
                [
                    'name' => $intesa->name,
                    'slug' => $intesa->slug,
                    'is_custom' => false,
                    'is_active' => true,
                ]
            );
        $revolutUserBank = $revolut === null
            ? null
            : UserBank::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bank_id' => $revolut->id,
                ],
                [
                    'name' => $revolut->name,
                    'slug' => $revolut->slug,
                    'is_custom' => false,
                    'is_active' => true,
                ]
            );

        $personalScope = Scope::where('user_id', $user->id)->where('name', 'Personale')->first();
        $home1Scope = Scope::where('user_id', $user->id)->where('name', 'Casa 1')->first();

        $mainPaymentAccount = Account::where('user_id', $user->id)
            ->where('name', 'Conto Intesa Personale')
            ->first();

        $accounts = [
            [
                'name' => 'Conto Intesa Personale',
                'bank_id' => $intesa?->id,
                'user_bank_id' => $intesaUserBank?->id,
                'account_type_id' => $paymentAccountType?->id,
                'scope_id' => $personalScope?->id,
                'currency' => 'EUR',
                'opening_balance' => 3200.00,
                'current_balance' => 3200.00,
                'is_manual' => false,
                'is_active' => true,
                'settings' => [
                    'allow_negative_balance' => false,
                ],
            ],
            [
                'name' => 'Carta Revolut',
                'bank_id' => $revolut?->id,
                'user_bank_id' => $revolutUserBank?->id,
                'account_type_id' => $paymentAccountType?->id,
                'scope_id' => $personalScope?->id,
                'currency' => 'EUR',
                'opening_balance' => 450.00,
                'current_balance' => 450.00,
                'is_manual' => false,
                'is_active' => true,
                'settings' => [
                    'allow_negative_balance' => false,
                ],
            ],
            [
                'name' => 'Cassa Casa 1',
                'bank_id' => null,
                'user_bank_id' => null,
                'account_type_id' => $cashAccountType?->id,
                'scope_id' => $home1Scope?->id,
                'currency' => 'EUR',
                'opening_balance' => 250.00,
                'current_balance' => 250.00,
                'is_manual' => true,
                'is_active' => true,
                'settings' => [
                    'allow_negative_balance' => false,
                ],
            ],
            [
                'name' => 'Carta di Credito Intesa',
                'bank_id' => $intesa?->id,
                'user_bank_id' => $intesaUserBank?->id,
                'account_type_id' => $creditCardType?->id,
                'scope_id' => $personalScope?->id,
                'currency' => 'EUR',
                'opening_balance' => 0.00,
                'current_balance' => 380.00,
                'is_manual' => false,
                'is_active' => true,
                'settings' => [
                    'credit_card' => [
                        'credit_limit' => 3000,
                        'linked_payment_account_id' => $mainPaymentAccount?->id,
                        'statement_closing_day' => 28,
                        'payment_day' => 15,
                        'auto_pay' => true,
                    ],
                ],
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
                    'user_bank_id' => $account['user_bank_id'],
                    'account_type_id' => $account['account_type_id'],
                    'scope_id' => $account['scope_id'],
                    'currency' => $account['currency'],
                    'opening_balance' => $account['opening_balance'],
                    'current_balance' => $account['current_balance'],
                    'is_manual' => $account['is_manual'],
                    'is_active' => $account['is_active'] ?? true,
                    'settings' => $account['settings'] ?? null,
                ]
            );
        }
    }
}
