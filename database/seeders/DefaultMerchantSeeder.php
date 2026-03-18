<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultMerchantSeeder extends Seeder
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

        $categoryMap = Category::where('user_id', $user->id)
            ->get()
            ->keyBy('name');

        $merchants = [
            ['name' => 'Sole365', 'category' => 'Alimentari'],
            ['name' => 'Decò', 'category' => 'Alimentari'],
            ['name' => 'Lidl', 'category' => 'Alimentari'],
            ['name' => 'Conad', 'category' => 'Alimentari'],
            ['name' => 'Enel Energia', 'category' => 'Luce'],
            ['name' => 'GORI', 'category' => 'Acqua'],
            ['name' => 'Q8', 'category' => 'Auto'],
            ['name' => 'Amazon', 'category' => 'Extra'],
            ['name' => 'Farmacia Centrale', 'category' => 'Salute'],
            ['name' => 'Condominio Via Roma', 'category' => 'Condominio'],
        ];

        foreach ($merchants as $merchant) {
            Merchant::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $merchant['name'],
                ],
                [
                    'normalized_name' => mb_strtolower($merchant['name']),
                    'default_category_id' => $categoryMap[$merchant['category']]->id ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
