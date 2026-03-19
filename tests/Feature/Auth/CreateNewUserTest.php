<?php

use App\Actions\Fortify\CreateNewUser;

test('create new user persists surname', function () {
    $action = new CreateNewUser;

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
});
