<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultBankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            'Intesa Sanpaolo',
            'Fineco',
            'Revolut',
            'Poste Italiane',
            'UniCredit',
        ];

        foreach ($banks as $name) {
            Bank::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'country_code' => 'IT',
                    'is_active' => true,
                ]
            );
        }
    }
}
