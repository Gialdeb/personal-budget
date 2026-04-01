<?php

use App\Http\Controllers\AssetVersionController;
use App\Http\Controllers\ChangelogFeedController;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\Sharing\AccountInvitationOnboardingController;
use App\Http\Controllers\Webhooks\KofiWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/manifest.webmanifest', PwaManifestController::class)
    ->name('pwa.manifest');
Route::get('/service-worker.js', ServiceWorkerController::class)
    ->name('pwa.service-worker');
Route::get('/asset-version', AssetVersionController::class)
    ->name('asset-version');
Route::post('/webhooks/kofi', KofiWebhookController::class)
    ->name('webhooks.kofi');

// PUBLIC ROUTE
Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');
Route::inertia('/features', 'Features', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('features');
Route::inertia('/pricing', 'Pricing', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('pricing');
Route::inertia('/about-me', 'AboutMe', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('about-me');
Route::inertia('/customers', 'Customers', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('customers');
Route::inertia('/download-app', 'DownloadApp', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('download-app');
Route::inertia('/changelog', 'changelog/Index', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('changelog.index');

Route::inertia('/terms-of-service', 'legal/TermsOfService')->name('terms-of-service');
Route::inertia('/privacy', 'legal/Privacy')->name('privacy');
Route::prefix('changelog/releases')
    ->name('changelog.releases.')
    ->group(function () {
        Route::get('/', [ChangelogFeedController::class, 'index'])->name('index');
        Route::get('/{versionLabel}', [ChangelogFeedController::class, 'show'])->name('show');
    });
Route::get('/changelog/{versionLabel}', function (string $versionLabel) {
    return inertia('changelog/Show', [
        'canRegister' => Features::enabled(Features::registration()),
        'versionLabel' => $versionLabel,
    ]);
})->name('changelog.show');

// LANGUAGE PATH
Route::patch('/settings/locale', [LocaleController::class, 'update'])
    ->name('settings.locale.update');

// SHARING ACCOUNTS
Route::prefix('account-invitations')->name('account-invitations.')->group(function () {
    Route::get('/{accountInvitation}/onboarding', [AccountInvitationOnboardingController::class, 'show'])
        ->name('onboarding.show');
    Route::post('/{accountInvitation}/register', [AccountInvitationOnboardingController::class, 'register'])
        ->name('register');

    Route::post('/{accountInvitation}/accept-authenticated', [AccountInvitationOnboardingController::class, 'acceptAuthenticated'])
        ->middleware(['auth'])
        ->name('accept-authenticated');
});

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
