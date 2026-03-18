<?php

namespace Database\Seeders;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Scope;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeTransactionSeeder extends Seeder
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
            ['date' => '2025-01-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio gennaio'],
            ['date' => '2025-01-08', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Sole365', 'direction' => 'expense', 'amount' => 86, 'description' => 'Spesa supermercato'],
            ['date' => '2025-01-11', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Auto', 'merchant' => 'Q8', 'direction' => 'expense', 'amount' => 50, 'description' => 'Carburante'],
            ['date' => '2025-01-15', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Luce', 'merchant' => 'Enel Energia', 'direction' => 'expense', 'amount' => 92, 'description' => 'Bolletta luce gennaio'],
            ['date' => '2025-01-22', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Condominio', 'merchant' => 'Condominio Via Roma', 'direction' => 'expense', 'amount' => 110, 'description' => 'Quota condominio gennaio'],

            ['date' => '2025-02-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio febbraio'],
            ['date' => '2025-02-09', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Decò', 'direction' => 'expense', 'amount' => 74, 'description' => 'Spesa alimentare'],
            ['date' => '2025-02-14', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Acqua', 'merchant' => 'GORI', 'direction' => 'expense', 'amount' => 43, 'description' => 'Bolletta acqua'],
            ['date' => '2025-02-21', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Extra', 'merchant' => 'Amazon', 'direction' => 'expense', 'amount' => 39, 'description' => 'Acquisto Amazon'],

            ['date' => '2025-03-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio marzo'],
            ['date' => '2025-03-10', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Lidl', 'direction' => 'expense', 'amount' => 92, 'description' => 'Spesa Lidl'],
            ['date' => '2025-03-18', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Salute', 'merchant' => 'Farmacia Centrale', 'direction' => 'expense', 'amount' => 28, 'description' => 'Farmacia'],
            ['date' => '2025-03-24', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Luce', 'merchant' => 'Enel Energia', 'direction' => 'expense', 'amount' => 88, 'description' => 'Bolletta luce marzo'],

            ['date' => '2025-04-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio aprile'],
            ['date' => '2025-04-09', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Conad', 'direction' => 'expense', 'amount' => 81, 'description' => 'Spesa Conad'],
            ['date' => '2025-04-12', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Auto', 'merchant' => 'Q8', 'direction' => 'expense', 'amount' => 65, 'description' => 'Carburante'],
            ['date' => '2025-04-23', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Condominio', 'merchant' => 'Condominio Via Roma', 'direction' => 'expense', 'amount' => 110, 'description' => 'Quota condominio aprile'],

            ['date' => '2025-05-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio maggio'],
            ['date' => '2025-05-11', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Extra', 'merchant' => 'Amazon', 'direction' => 'expense', 'amount' => 62, 'description' => 'Accessori'],
            ['date' => '2025-05-20', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Acqua', 'merchant' => 'GORI', 'direction' => 'expense', 'amount' => 46, 'description' => 'Bolletta acqua maggio'],

            ['date' => '2025-06-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio giugno'],
            ['date' => '2025-06-08', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Sole365', 'direction' => 'expense', 'amount' => 95, 'description' => 'Spesa giugno'],
            ['date' => '2025-06-18', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Salute', 'merchant' => 'Farmacia Centrale', 'direction' => 'expense', 'amount' => 34, 'description' => 'Farmacia giugno'],
            ['date' => '2025-06-26', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Luce', 'merchant' => 'Enel Energia', 'direction' => 'expense', 'amount' => 96, 'description' => 'Bolletta giugno'],

            ['date' => '2025-07-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio luglio'],
            ['date' => '2025-07-10', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Decò', 'direction' => 'expense', 'amount' => 79, 'description' => 'Spesa luglio'],
            ['date' => '2025-07-14', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Auto', 'merchant' => 'Q8', 'direction' => 'expense', 'amount' => 58, 'description' => 'Benzina luglio'],

            ['date' => '2025-08-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio agosto'],
            ['date' => '2025-08-09', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Extra', 'merchant' => 'Amazon', 'direction' => 'expense', 'amount' => 44, 'description' => 'Ordine agosto'],
            ['date' => '2025-08-21', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Condominio', 'merchant' => 'Condominio Via Roma', 'direction' => 'expense', 'amount' => 110, 'description' => 'Quota condominio agosto'],

            ['date' => '2025-09-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio settembre'],
            ['date' => '2025-09-08', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Lidl', 'direction' => 'expense', 'amount' => 88, 'description' => 'Spesa settembre'],
            ['date' => '2025-09-17', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Acqua', 'merchant' => 'GORI', 'direction' => 'expense', 'amount' => 41, 'description' => 'Bolletta settembre'],

            ['date' => '2025-10-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio ottobre'],
            ['date' => '2025-10-11', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Salute', 'merchant' => 'Farmacia Centrale', 'direction' => 'expense', 'amount' => 31, 'description' => 'Farmacia ottobre'],
            ['date' => '2025-10-19', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Luce', 'merchant' => 'Enel Energia', 'direction' => 'expense', 'amount' => 89, 'description' => 'Bolletta ottobre'],

            ['date' => '2025-11-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio novembre'],
            ['date' => '2025-11-10', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Conad', 'direction' => 'expense', 'amount' => 91, 'description' => 'Spesa novembre'],
            ['date' => '2025-11-15', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Extra', 'merchant' => 'Amazon', 'direction' => 'expense', 'amount' => 53, 'description' => 'Acquisto novembre'],

            ['date' => '2025-12-05', 'account' => 'Conto Intesa Personale', 'scope' => 'Personale', 'category' => 'Stipendio', 'merchant' => null, 'direction' => 'income', 'amount' => 1800, 'description' => 'Stipendio dicembre'],
            ['date' => '2025-12-12', 'account' => 'Carta Revolut', 'scope' => 'Personale', 'category' => 'Alimentari', 'merchant' => 'Sole365', 'direction' => 'expense', 'amount' => 99, 'description' => 'Spesa dicembre'],
            ['date' => '2025-12-20', 'account' => 'Conto Intesa Personale', 'scope' => 'Casa 1', 'category' => 'Condominio', 'merchant' => 'Condominio Via Roma', 'direction' => 'expense', 'amount' => 110, 'description' => 'Quota condominio dicembre'],
        ];

        foreach ($rows as $row) {
            Transaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'account_id' => $accounts[$row['account']]->id ?? null,
                    'transaction_date' => $row['date'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                ],
                [
                    'scope_id' => $scopes[$row['scope']]->id ?? null,
                    'category_id' => $categories[$row['category']]->id ?? null,
                    'merchant_id' => $row['merchant'] ? ($merchants[$row['merchant']]->id ?? null) : null,
                    'direction' => TransactionDirectionEnum::from($row['direction']),
                    'currency' => 'EUR',
                    'source_type' => TransactionSourceTypeEnum::MANUAL,
                    'status' => TransactionStatusEnum::CONFIRMED,
                    'is_transfer' => false,
                ]
            );
        }
    }
}
