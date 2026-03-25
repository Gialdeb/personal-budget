<?php

use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\Sharing\AccountInvitationOnboardingController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// PUBLIC ROUTE
Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');
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
