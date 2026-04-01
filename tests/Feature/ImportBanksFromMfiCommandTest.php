<?php

use App\Models\Bank;
use Illuminate\Support\Facades\File;

test('mfi import command reads utf16 tsv and upserts banks without duplicates', function () {
    $path = storage_path('framework/testing/mfi-test.tsv');

    $utf8Dataset = implode("\n", [
        'RIAD_CODE	LEI	COUNTRY_OF_REGISTRATION	NAME	BOX	ADDRESS	POSTAL	CITY	CATEGORY	HEAD_COUNTRY_OF_REGISTRATION	HEAD_NAME	HEAD_RIAD_CODE	HEAD_LEI	REPORT',
        'IT0001	529900AAA	IT	Banca Uno	10	Via Roma	00100	Roma	Credit Institution			'."\t".'	Euro Area reporter',
        'IT0001	529900AAA	IT	Banca Uno Aggiornata	10	Via Roma 2	00100	Roma	Credit Institution			'."\t".'	Euro Area reporter',
        '	529900BBB	IT	Banca Due		Via Milano	20100	Milano	Other Institution			'."\t".'	Euro Area reporter',
    ]);

    File::ensureDirectoryExists(dirname($path));
    File::put($path, mb_convert_encoding($utf8Dataset, 'UTF-16', 'UTF-8'));

    $this->artisan('banks:import-mfi', ['path' => $path])
        ->expectsOutputToContain('MFI bank import completed.')
        ->assertExitCode(0);

    expect(Bank::query()->count())->toBe(2);

    $this->assertDatabaseHas('banks', [
        'riad_code' => 'IT0001',
        'country_code' => 'IT',
        'name' => 'Banca Uno Aggiornata',
        'address' => '10, Via Roma 2',
        'report_label' => 'Euro Area reporter',
    ]);

    $this->assertDatabaseHas('banks', [
        'country_code' => 'IT',
        'slug' => 'banca-due',
        'name' => 'Banca Due',
        'city' => 'Milano',
    ]);
});
