<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Route::middleware('web')->get('/_test/notification-feedback', function () {
        return Inertia::render('Welcome');
    });
});

test('shares contextual notification messages for every supported level', function () {
    $messages = ['success' => 'Salvato', 'error' => 'Errore', 'warning' => 'Controlla i dati', 'warn' => 'Attenzione', 'info' => 'Informazione', 'status' => 'Operazione in attesa'];
    $this->withSession($messages)->get('/_test/notification-feedback')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('flash', $messages));
});

test('shares validation messages even for fields absent from the visible form', function () {
    $request = Request::create('/_test/notification-feedback');
    $session = app('session')->driver();
    $session->put('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'hidden_account_uuid' => ['Seleziona un conto valido.'], 'translations.en.title' => ['Compila il titolo inglese.'],
    ])));
    $request->setLaravelSession($session);
    $shared = app(HandleInertiaRequests::class)->share($request);
    $errors = ($shared['errors'])();
    expect($errors->hidden_account_uuid)->toBe('Seleziona un conto valido.')
        ->and($errors->{'translations.en.title'})->toBe('Compila il titolo inglese.');
});
