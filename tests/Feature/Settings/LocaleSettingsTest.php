<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can update locale', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);

    $this->actingAs($user)
        ->patch(route('settings.locale.update'), [
            'locale' => 'en',
        ])
        ->assertStatus(303);

    expect($user->fresh()->locale)->toBe('en')
        ->and(session('locale'))->toBe('en');
});

test('authenticated user locale has priority over session and browser locale', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['locale' => 'it'])
        ->get('/dashboard', [
            'Accept-Language' => 'it-IT,it;q=0.9',
        ]);

    $response->assertInertia(fn ($page) => $page
        ->where('locale.current', 'en')
        ->where('locale.fallback', 'en')
        ->has('locale.available', 2)
        ->where('locale.currencies.EUR.code', 'EUR')
        ->where('locale.currencies.EUR.symbol', '€')
        ->where('locale.currencies.EUR.minor_unit', 2)
    );
});

test('guest session locale has priority over browser locale', function () {
    $this->withSession(['locale' => 'it'])
        ->get('/', [
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
        ->assertInertia(fn ($page) => $page
            ->where('locale.current', 'it')
        );
});

test('guest without session uses accept language locale', function () {
    $this->get('/', [
        'Accept-Language' => 'en-US,en;q=0.9,it;q=0.8',
    ])->assertInertia(fn ($page) => $page
        ->where('locale.current', 'en')
    );
});

test('unsupported browser locale falls back to default locale', function () {
    $this->get('/', [
        'Accept-Language' => 'fr-FR,fr;q=0.9,de-DE;q=0.8',
    ])->assertInertia(fn ($page) => $page
        ->where('locale.current', 'it')
    );
});

test('guest can update locale and it is stored in session', function () {
    $this->patch(route('settings.locale.update'), [
        'locale' => 'en',
    ])->assertStatus(303);

    expect(session('locale'))->toBe('en');
});

test('inertia shares the resolved locale for guests', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('locale.current', 'en')
            ->where('locale.fallback', 'en')
            ->has('locale.available', 2)
            ->where('locale.currencies.USD.code', 'USD')
            ->where('locale.currencies.USD.symbol', '$')
        );
});

test('unsupported locale is rejected', function () {
    $this->patch(route('settings.locale.update'), [
        'locale' => 'fr',
    ])
        ->assertSessionHasErrors('locale');
});
