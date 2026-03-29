<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\BankController;
use App\Http\Controllers\Settings\CategoryController;
use App\Http\Controllers\Settings\ImpersonationConsentController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SharedCategoryController;
use App\Http\Controllers\Settings\TrackedItemController;
use App\Http\Controllers\Settings\UserCurrencyController;
use App\Http\Controllers\Settings\UserYearController;
use Illuminate\Support\Facades\Route;

// SETTINGS PROFILE
Route::middleware(['auth', 'verified', 'not_banned', 'role:admin|user'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    // SETTINGS PROFILE
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/profile/avatar/{user:uuid}', [ProfileController::class, 'avatar'])
        ->name('profile.avatar.show');
    Route::patch('settings/profile/notification-preferences', [ProfileController::class, 'updateNotificationPreferences'])
        ->name('settings.profile.notification-preferences.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/settings/profile/impersonation-consent', [ImpersonationConsentController::class, 'update'])
        ->name('settings.profile.impersonation-consent.update');
    Route::patch('/settings/profile/currency', [UserCurrencyController::class, 'update'])
        ->name('settings.profile.update-currency');

    // SETTINGS SECURITY
    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    // SETTINGS BANKS
    Route::get('settings/banks', [BankController::class, 'index'])->name('banks.edit');
    Route::post('settings/banks', [BankController::class, 'store'])->name('banks.store');
    Route::patch('settings/banks/{userBank:uuid}', [BankController::class, 'update'])->name('banks.update');
    Route::patch('settings/banks/{userBank:uuid}/toggle-active', [BankController::class, 'toggleActive'])
        ->name('banks.toggle-active');
    Route::delete('settings/banks/{userBank:uuid}', [BankController::class, 'destroy'])->name('banks.destroy');
    // SETTINGS ACCOUNTS
    Route::get('settings/accounts', [AccountController::class, 'index'])->name('accounts.edit');
    Route::post('settings/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('settings/accounts/{account:uuid}', [AccountController::class, 'update'])->name('accounts.update');
    Route::patch('settings/accounts/{account:uuid}/toggle-active', [AccountController::class, 'toggleActive'])
        ->name('accounts.toggle-active');
    Route::delete('settings/accounts/{account:uuid}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    // SETTINGS YEARS
    Route::get('settings/years', [UserYearController::class, 'index'])->name('years.edit');
    Route::post('settings/years', [UserYearController::class, 'store'])->name('years.store');
    Route::patch('settings/years/{userYear:uuid}', [UserYearController::class, 'update'])->name('years.update');
    Route::patch('settings/years/{userYear:uuid}/activate', [UserYearController::class, 'activate'])->name('years.activate');
    Route::delete('settings/years/{userYear:uuid}', [UserYearController::class, 'destroy'])->name('years.destroy');
    // SETTINGS CATEGORIES
    Route::get('settings/categories', [CategoryController::class, 'index'])->name('categories.edit');
    Route::post('settings/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('settings/categories/{category:uuid}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('settings/categories/{category:uuid}/toggle-active', [CategoryController::class, 'toggleActive'])
        ->name('categories.toggle-active');
    Route::delete('settings/categories/{category:uuid}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('settings/shared-categories', [SharedCategoryController::class, 'index'])->name('shared-categories.edit');
    Route::post('settings/shared-categories/{account:uuid}', [SharedCategoryController::class, 'store'])->name('shared-categories.store');
    Route::post('settings/shared-categories/{account:uuid}/materialize-personal', [SharedCategoryController::class, 'materialize'])->name('shared-categories.materialize');
    Route::patch('settings/shared-categories/{account:uuid}/{category:uuid}', [SharedCategoryController::class, 'update'])->name('shared-categories.update');
    Route::patch('settings/shared-categories/{account:uuid}/{category:uuid}/toggle-active', [SharedCategoryController::class, 'toggleActive'])
        ->name('shared-categories.toggle-active');
    Route::delete('settings/shared-categories/{account:uuid}/{category:uuid}', [SharedCategoryController::class, 'destroy'])->name('shared-categories.destroy');
    // SETTINGS TRACKED ITEMS
    Route::get('settings/tracked-items', [TrackedItemController::class, 'index'])->name('tracked-items.edit');
    Route::post('settings/tracked-items', [TrackedItemController::class, 'store'])->name('tracked-items.store');
    Route::post('settings/tracked-items/shared/{account:uuid}/materialize-personal', [TrackedItemController::class, 'materialize'])
        ->name('tracked-items.materialize');
    Route::patch('settings/tracked-items/{trackedItem:uuid}', [TrackedItemController::class, 'update'])->name('tracked-items.update');
    Route::patch('settings/tracked-items/{trackedItem:uuid}/toggle-active', [TrackedItemController::class, 'toggleActive'])
        ->name('tracked-items.toggle-active');
    Route::delete('settings/tracked-items/{trackedItem:uuid}', [TrackedItemController::class, 'destroy'])->name('tracked-items.destroy');
});
