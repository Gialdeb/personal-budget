<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Services\Banks\BankMfiImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DefaultBankSeeder extends Seeder
{
    public function run(): void
    {
        $datasetPath = database_path('seeders/data/mfi_banks.tsv');

        if (is_file($datasetPath)) {
            app(BankMfiImportService::class)->importFromPath($datasetPath);

            return;
        }

        foreach ($this->fallbackBanks() as $bank) {
            Bank::updateOrCreate(
                [
                    'country_code' => $bank['country_code'],
                    'slug' => $bank['slug'],
                ],
                [
                    'name' => $bank['name'],
                    'riad_code' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function fallbackBanks(): Collection
    {
        return collect([
            ['name' => 'Intesa Sanpaolo', 'country_code' => 'IT'],
            ['name' => 'Fineco', 'country_code' => 'IT'],
            ['name' => 'Revolut', 'country_code' => 'LT'],
            ['name' => 'Poste Italiane', 'country_code' => 'IT'],
            ['name' => 'UniCredit', 'country_code' => 'IT'],
        ])->map(fn (array $bank): array => [
            ...$bank,
            'slug' => Str::slug($bank['name']),
        ]);
    }
}
