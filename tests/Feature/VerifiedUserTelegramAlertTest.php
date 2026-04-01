<?php

use App\Listeners\SendVerifiedUserTelegramAlert;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('sends a telegram alert when a user verifies their email', function () {
    config()->set('services.telegram.enabled', true);
    config()->set('services.telegram.bot_token', 'telegram-token');
    config()->set('services.telegram.chat_id', '123456');

    Http::fake();

    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'locale' => 'it',
        'email_verified_at' => now(),
    ]);

    app(SendVerifiedUserTelegramAlert::class)->handle(new Verified($user));

    $recorded = Http::recorded();

    expect($recorded)->toHaveCount(1);

    [$request] = $recorded[0];
    $data = $request->data();

    expect($request->url())->toContain('api.telegram.org/bottelegram-token/sendMessage')
        ->and($data['chat_id'] ?? null)->toBe('123456')
        ->and($data['parse_mode'] ?? null)->toBe('HTML')
        ->and($data['text'] ?? '')->toContain('Nuovo utente verificato')
        ->and($data['text'] ?? '')->toContain('mario@example.com')
        ->and($data['text'] ?? '')->toContain('Mario Rossi')
        ->and($data['text'] ?? '')->toContain((string) $user->id)
        ->and($data['text'] ?? '')->toContain('testing')
        ->and($data['text'] ?? '')->toContain('https://soamco.lo');
});

it('does not send a telegram alert when telegram notifications are disabled', function () {
    config()->set('services.telegram.enabled', false);

    Http::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(SendVerifiedUserTelegramAlert::class)->handle(new Verified($user));

    Http::assertNothingSent();
});
