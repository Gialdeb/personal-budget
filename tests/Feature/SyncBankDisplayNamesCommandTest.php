<?php

use App\Models\Bank;

test('sync bank display names command is idempotent and preserves official names', function () {
    $bank = Bank::query()->create([
        'name' => 'BANCA MONTE DEI PASCHI DI SIENA S.P.A.',
        'slug' => 'banca-mps',
        'country_code' => 'IT',
        'is_active' => true,
        'display_name' => null,
    ]);

    $this->artisan('banks:sync-display-names')
        ->expectsOutputToContain('Display names synchronized.')
        ->assertExitCode(0);

    expect($bank->fresh()?->name)->toBe('BANCA MONTE DEI PASCHI DI SIENA S.P.A.')
        ->and($bank->fresh()?->display_name)->toBe('Monte dei Paschi');

    $this->artisan('banks:sync-display-names')
        ->expectsOutputToContain('Updated: 0')
        ->assertExitCode(0);

    expect($bank->fresh()?->display_name)->toBe('Monte dei Paschi');
});

test('sync bank display names command truncates very long cleaned names safely', function () {
    $bank = Bank::query()->create([
        'name' => 'SUMITOMO MITSUI BANKING CORPORATION FIL. DÜSSELDORF ZWEIGNIEDERLASSUNG DER SUMITOMO MITSUI BANKING CORPORATION MIT SITZ IN TOKIO',
        'slug' => 'sumitomo-mitsui',
        'country_code' => 'DE',
        'is_active' => true,
        'display_name' => null,
    ]);

    $this->artisan('banks:sync-display-names')
        ->expectsOutputToContain('Display names synchronized.')
        ->assertExitCode(0);

    $displayName = $bank->fresh()?->display_name;

    expect($displayName)->not->toBeNull()
        ->and(mb_strlen((string) $displayName))->toBeLessThanOrEqual(120)
        ->and($displayName)->toStartWith('Sumitomo Mitsui Banking Corporation');
});
