<?php

namespace Database\Seeders;

use App\Enums\MerchantAliasMatchTypeEnum;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MerchantAliasSeeder extends Seeder
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

        $merchants = Merchant::where('user_id', $user->id)->get()->keyBy('name');

        $rows = [
            ['merchant' => 'Sole365', 'alias' => 'SOLE365'],
            ['merchant' => 'Sole365', 'alias' => 'SOLE 365'],
            ['merchant' => 'Decò', 'alias' => 'DECO'],
            ['merchant' => 'Decò', 'alias' => 'DECO SUPERMERCATI'],
            ['merchant' => 'Lidl', 'alias' => 'LIDL'],
            ['merchant' => 'Conad', 'alias' => 'CONAD'],
            ['merchant' => 'Enel Energia', 'alias' => 'ENEL'],
            ['merchant' => 'Enel Energia', 'alias' => 'ENEL ENERGIA'],
            ['merchant' => 'GORI', 'alias' => 'GORI'],
            ['merchant' => 'Q8', 'alias' => 'Q8'],
            ['merchant' => 'Amazon', 'alias' => 'AMAZON'],
            ['merchant' => 'Farmacia Centrale', 'alias' => 'FARMACIA CENTRALE'],
            ['merchant' => 'Condominio Via Roma', 'alias' => 'CONDOMINIO VIA ROMA'],
        ];

        foreach ($rows as $index => $row) {
            $merchant = $merchants[$row['merchant']] ?? null;

            if (! $merchant) {
                continue;
            }

            MerchantAlias::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'alias' => $row['alias'],
                ],
                [
                    'normalized_alias' => mb_strtolower($row['alias']),
                    'match_type' => MerchantAliasMatchTypeEnum::CONTAINS,
                    'priority' => 100 + $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
