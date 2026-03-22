<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;

test('create new user persists surname and provisions a default cash account', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($user->surname)->toBe('Rossi');

    $this->assertDatabaseHas('users', [
        'email' => 'mario@example.com',
        'surname' => 'Rossi',
    ]);

    $cashAccount = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cassa contanti')
        ->first();

    expect($cashAccount)->not->toBeNull();
    expect((float) $cashAccount->opening_balance)->toBe(0.0);
    expect((float) $cashAccount->current_balance)->toBe(0.0);
    expect(data_get($cashAccount->settings, 'allow_negative_balance'))->toBeFalse();
    expect($user->settings?->active_year)->toBe(2026);

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
});
