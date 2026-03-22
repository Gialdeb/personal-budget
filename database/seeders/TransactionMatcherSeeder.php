<?php

namespace Database\Seeders;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionMatcherFieldEnum;
use App\Enums\TransactionMatcherTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Scope;
use App\Models\TransactionMatcher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionMatcherSeeder extends Seeder
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
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');
        $merchants = Merchant::where('user_id', $user->id)->get()->keyBy('name');
        $scopes = Scope::where('user_id', $user->id)->get()->keyBy('name');

        $rows = [
            [
                'pattern' => 'ENEL',
                'category' => 'Luce',
                'merchant' => 'Enel Energia',
                'scope' => 'Casa 1',
                'account' => 'Conto Intesa Personale',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'GORI',
                'category' => 'Acqua',
                'merchant' => 'GORI',
                'scope' => 'Casa 1',
                'account' => 'Conto Intesa Personale',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'Q8',
                'category' => 'Auto',
                'merchant' => 'Q8',
                'scope' => 'Personale',
                'account' => 'Carta Revolut',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'AMAZON',
                'category' => 'Extra',
                'merchant' => 'Amazon',
                'scope' => 'Personale',
                'account' => 'Carta Revolut',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'SOLE365',
                'category' => 'Alimentari',
                'merchant' => 'Sole365',
                'scope' => 'Personale',
                'account' => 'Carta Revolut',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'DECO',
                'category' => 'Alimentari',
                'merchant' => 'Decò',
                'scope' => 'Personale',
                'account' => 'Carta Revolut',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'CONDOMINIO',
                'category' => 'Condominio',
                'merchant' => 'Condominio Via Roma',
                'scope' => 'Casa 1',
                'account' => 'Conto Intesa Personale',
                'direction' => TransactionDirectionEnum::EXPENSE,
            ],
            [
                'pattern' => 'STIPENDIO',
                'category' => 'Stipendio',
                'merchant' => null,
                'scope' => 'Personale',
                'account' => 'Conto Intesa Personale',
                'direction' => TransactionDirectionEnum::INCOME,
            ],
        ];

        foreach ($rows as $index => $row) {
            TransactionMatcher::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'pattern' => $row['pattern'],
                    'match_field' => TransactionMatcherFieldEnum::BANK_DESCRIPTION_RAW,
                ],
                [
                    'bank_id' => null,
                    'account_id' => $accounts[$row['account']]->id ?? null,
                    'merchant_id' => $row['merchant'] ? ($merchants[$row['merchant']]->id ?? null) : null,
                    'category_id' => $categories[$row['category']]->id ?? null,
                    'scope_id' => $scopes[$row['scope']]->id ?? null,
                    'direction' => $row['direction'],
                    'match_type' => TransactionMatcherTypeEnum::CONTAINS,
                    'normalized_pattern' => mb_strtolower($row['pattern']),
                    'confidence_score' => 95.00,
                    'auto_confirm' => true,
                    'priority' => 100 + $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
