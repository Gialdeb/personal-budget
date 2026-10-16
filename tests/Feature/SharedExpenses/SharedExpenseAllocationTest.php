<?php

use App\Services\SharedExpenses\SharedExpenseAllocation;
use Illuminate\Validation\ValidationException;

test('shared allocation defaults to an exact fifty fifty split and recalculates for changed totals', function () {
    $service = app(SharedExpenseAllocation::class);

    expect($service->normalize('100.00', 'self', null, null))->toMatchArray(['my_share_cents' => 5000, 'counterparty_share_cents' => 5000])
        ->and($service->normalize('215.00', 'self', null, null))->toMatchArray(['my_share_cents' => 10750, 'counterparty_share_cents' => 10750]);
});

test('shared allocation splits an odd cent deterministically and supports custom shares and both payers', function () {
    $service = app(SharedExpenseAllocation::class);

    expect($service->normalize('100.01', 'self', null, null))->toMatchArray(['paid_by' => 'self', 'my_share_cents' => 5000, 'counterparty_share_cents' => 5001])
        ->and($service->normalize('100.00', 'counterparty', '30.00', '70.00'))->toMatchArray(['paid_by' => 'counterparty', 'my_share_cents' => 3000, 'counterparty_share_cents' => 7000]);
});

test('shared allocation rejects shares exceeding or not matching the total', function (string $myShare, string $counterpartyShare) {
    app(SharedExpenseAllocation::class)->normalize('100.00', 'self', $myShare, $counterpartyShare);
})->with([
    ['101.00', '0.00'],
    ['30.00', '80.00'],
    ['30.00', '60.00'],
])->throws(ValidationException::class);

test('rescaling uses the original shares and keeps every cent allocated', function (?int $mine, ?int $other, int $expectedMine, int $expectedOther) {
    expect(app(SharedExpenseAllocation::class)->rescale('10.01', $mine, $other))
        ->toBe(['my_share_cents' => $expectedMine, 'counterparty_share_cents' => $expectedOther]);
})->with([
    'equal' => [400, 400, 500, 501],
    'custom' => [200, 600, 250, 751],
    'zero own share' => [0, 800, 0, 1001],
    'zero other share' => [800, 0, 1001, 0],
    'missing shares' => [null, null, 500, 501],
    'zero original total' => [0, 0, 500, 501],
]);
