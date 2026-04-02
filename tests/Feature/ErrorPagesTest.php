<?php

use App\Models\User;
use Illuminate\Http\Request;

function renderErrorPage(string $status, array $headers = []): string
{
    $request = Request::create("/_test/errors/{$status}", 'GET', server: array_merge([
        'HTTP_ACCEPT_LANGUAGE' => 'it-IT,it;q=0.9',
    ], $headers));

    app()->instance('request', $request);
    app('url')->setRequest($request);

    return view("errors.{$status}")->render();
}

dataset('localized_error_pages', [
    '403 it' => ['403', 'it', 'Accesso non consentito', 'Non hai i permessi necessari per visualizzare questa pagina.'],
    '403 en' => ['403', 'en', 'Access denied', 'You do not have permission to view this page.'],
    '404 it' => ['404', 'it', 'Pagina non trovata', 'La pagina che stai cercando non è disponibile o potrebbe essere stata spostata.'],
    '404 en' => ['404', 'en', 'Page not found', 'The page you are looking for is not available or may have been moved.'],
    '419 it' => ['419', 'it', 'Sessione scaduta', 'La pagina è scaduta o il modulo è rimasto aperto troppo a lungo. Ricarica la pagina e riprova.'],
    '419 en' => ['419', 'en', 'Session expired', 'The page expired or the form stayed open too long. Reload the page and try again.'],
    '429 it' => ['429', 'it', 'Troppe richieste', 'Hai effettuato troppe richieste in poco tempo. Attendi un momento e riprova.'],
    '429 en' => ['429', 'en', 'Too many requests', 'Too many requests were made in a short time. Please wait a moment and try again.'],
    '500 it' => ['500', 'it', 'Qualcosa è andato storto', 'Si è verificato un problema interno. Stiamo lavorando per risolverlo.'],
    '500 en' => ['500', 'en', 'Something went wrong', 'An internal problem occurred. We’re working to fix it.'],
    '503 it' => ['503', 'it', 'Siamo in manutenzione', 'Stiamo effettuando un aggiornamento o un intervento tecnico. Torna tra poco.'],
    '503 en' => ['503', 'en', 'We’re under maintenance', 'We’re performing an update or technical maintenance. Please check back soon.'],
]);

test('error pages render localized copy', function (
    string $status,
    string $locale,
    string $expectedTitle,
    string $expectedMessage,
) {
    app()->setLocale($locale);

    $html = renderErrorPage($status, [
        'HTTP_ACCEPT_LANGUAGE' => $locale === 'it' ? 'it-IT,it;q=0.9' : 'en-US,en;q=0.9',
    ]);

    expect($html)->toContain($expectedTitle)
        ->toContain($expectedMessage)
        ->toContain('Soamco Budget');
})->with('localized_error_pages');

test('error pages show dashboard cta only for authenticated users when useful', function (string $status) {
    $user = User::factory()->create();

    $this->actingAs($user);

    $authenticatedHtml = renderErrorPage($status);

    expect($authenticatedHtml)->toContain(route('dashboard'))
        ->toContain(route('home'));

    auth()->logout();

    $guestHtml = renderErrorPage($status);

    expect($guestHtml)->not->toContain(route('dashboard'))
        ->toContain(route('home'));
})->with(['403', '404', '419', '429', '500']);

test('page expired error includes a reload action', function () {
    expect(renderErrorPage('419'))->toContain('window.location.reload()');
});

test('maintenance page avoids dashboard and home ctas', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $html = renderErrorPage('503');

    expect($html)->not->toContain(route('dashboard'))
        ->not->toContain(route('home'))
        ->toContain('window.location.reload()');
});

test('error pages fall back to request preferred language when the app locale is unsupported', function () {
    app()->setLocale('fr');

    $html = renderErrorPage('403', [
        'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
    ]);

    expect($html)->toContain('Access denied')
        ->toContain('You do not have permission to view this page.');
});

test('maintenance page uses browser english when no authenticated user locale is available', function () {
    app()->setLocale('it');

    $html = renderErrorPage('503', [
        'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
    ]);

    expect($html)->toContain('We’re under maintenance')
        ->toContain('We’re performing an update or technical maintenance. Please check back soon.');
});

test('authenticated user locale wins over browser language in error pages', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    $this->actingAs($user);

    $html = renderErrorPage('404', [
        'HTTP_ACCEPT_LANGUAGE' => 'it-IT,it;q=0.9',
    ]);

    expect($html)->toContain('Page not found')
        ->not->toContain('Pagina non trovata');
});
