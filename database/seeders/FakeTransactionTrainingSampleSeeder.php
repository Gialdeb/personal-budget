<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Scope;
use App\Models\TransactionTrainingSample;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeTransactionTrainingSampleSeeder extends Seeder
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

        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');
        $merchants = Merchant::where('user_id', $user->id)->get()->keyBy('name');
        $scopes = Scope::where('user_id', $user->id)->get()->keyBy('name');

        $rows = [
            [
                'raw_description' => 'PAGAMENTO POS SOLE365 NAPOLI',
                'clean_description' => 'sole365 napoli',
                'normalized_signature' => 'sole365',
                'category' => 'Alimentari',
                'merchant' => 'Sole365',
                'scope' => 'Personale',
            ],
            [
                'raw_description' => 'ADDEBITO ENEL ENERGIA',
                'clean_description' => 'enel energia',
                'normalized_signature' => 'enel',
                'category' => 'Luce',
                'merchant' => 'Enel Energia',
                'scope' => 'Casa 1',
            ],
            [
                'raw_description' => 'RIFORNIMENTO Q8',
                'clean_description' => 'q8',
                'normalized_signature' => 'q8',
                'category' => 'Auto',
                'merchant' => 'Q8',
                'scope' => 'Personale',
            ],
        ];

        foreach ($rows as $row) {
            TransactionTrainingSample::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'raw_description' => $row['raw_description'],
                ],
                [
                    'bank_id' => null,
                    'account_id' => null,
                    'clean_description' => $row['clean_description'],
                    'normalized_signature' => $row['normalized_signature'],
                    'category_id' => $categories[$row['category']]->id ?? null,
                    'merchant_id' => $merchants[$row['merchant']]->id ?? null,
                    'scope_id' => $scopes[$row['scope']]->id ?? null,
                    'confirmed_by_user' => true,
                    'usage_count' => 1,
                    'last_seen_at' => now(),
                ]
            );
        }
    }
}
