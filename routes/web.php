<?php

use App\Http\Controllers\AdminInvitationAcceptanceController;
use App\Http\Controllers\AssetVersionController;
use App\Http\Controllers\ChangelogFeedController;
use App\Http\Controllers\HelpCenterArticleController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\HelpCenterSectionController;
use App\Http\Controllers\MaintenanceStatusController;
use App\Http\Controllers\PublicChangelogPageController;
use App\Http\Controllers\PublicEditorialAssetController;
use App\Http\Controllers\PublicSitemapController;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\SessionIdentityController;
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
Route::get('/session/identity', SessionIdentityController::class)
    ->name('session.identity');
Route::get('/maintenance/status', MaintenanceStatusController::class)
    ->name('maintenance.status');
Route::post('/webhooks/kofi', KofiWebhookController::class)
    ->name('webhooks.kofi');
Route::get('/sitemap.xml', PublicSitemapController::class)->name('sitemap');
Route::get('/editorial-assets', PublicEditorialAssetController::class)
    ->name('editorial-assets.show');

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
Route::get('/changelog', [PublicChangelogPageController::class, 'index'])
    ->name('changelog.index');
Route::get('/help-center', [HelpCenterController::class, 'index'])
    ->name('help-center.index');
Route::get('/help-center/sections/{knowledgeSection:slug}', [HelpCenterSectionController::class, 'show'])
    ->name('help-center.sections.show');
Route::get('/help-center/articles/{knowledgeArticle:slug}', [HelpCenterArticleController::class, 'show'])
    ->name('help-center.articles.show');

Route::inertia('/terms-of-service', 'legal/TermsOfService')->name('terms-of-service');
Route::inertia('/privacy', 'legal/Privacy')->name('privacy');
Route::prefix('changelog/releases')
    ->name('changelog.releases.')
    ->group(function () {
        Route::get('/', [ChangelogFeedController::class, 'index'])->name('index');
        Route::get('/{versionLabel}', [ChangelogFeedController::class, 'show'])->name('show');
    });
Route::get('/changelog/{versionLabel}', [PublicChangelogPageController::class, 'show'])
    ->name('changelog.show');

// LANGUAGE PATH
Route::patch('/settings/locale', [LocaleController::class, 'update'])
    ->name('settings.locale.update');

// SHARING ACCOUNTS
Route::prefix('account-invitations')->name('account-invitations.')->group(function () {
    Route::get('/{accountInvitation}/onboarding', [AccountInvitationOnboardingController::class, 'show'])
        ->whereUuid('accountInvitation')->name('onboarding.show');
    Route::post('/{accountInvitation}/register', [AccountInvitationOnboardingController::class, 'register'])
        ->whereUuid('accountInvitation')->name('register');

    Route::post('/{accountInvitation}/accept-authenticated', [AccountInvitationOnboardingController::class, 'acceptAuthenticated'])
        ->middleware(['auth'])
        ->whereUuid('accountInvitation')->name('accept-authenticated');
});

Route::middleware(['auth', 'verified', 'not_banned'])->prefix('admin-invitations')->name('admin-invitations.')->group(function () {
    Route::get('/{adminInvitation:uuid}', [AdminInvitationAcceptanceController::class, 'show'])
        ->whereUuid('adminInvitation')->name('show');
    Route::post('/{adminInvitation:uuid}', [AdminInvitationAcceptanceController::class, 'store'])
        ->whereUuid('adminInvitation')->middleware('throttle:5,1')->name('accept');
});

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
