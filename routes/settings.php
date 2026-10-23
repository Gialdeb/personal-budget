<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\BankController;
use App\Http\Controllers\Settings\CategoryController;
use App\Http\Controllers\Settings\ExchangeRateController;
use App\Http\Controllers\Settings\ExportController;
use App\Http\Controllers\Settings\ImpersonationConsentController;
use App\Http\Controllers\Settings\MoveTrackedItemMovementsController;
use App\Http\Controllers\Settings\OnboardingController;
use App\Http\Controllers\Settings\PendingCategoryController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SharedCategoryController;
use App\Http\Controllers\Settings\TrackedItemController;
use App\Http\Controllers\Settings\TransactionTemplateController;
use App\Http\Controllers\Settings\UserCurrencyController;
use App\Http\Controllers\Settings\UserYearController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\TrackedItemLogoController;
use Illuminate\Support\Facades\Route;

// SETTINGS PROFILE
Route::middleware(['auth', 'verified', 'not_banned', 'role:admin|user'])->group(function () {
    Route::post('settings/onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');

    Route::get('settings/quick-actions', [TransactionTemplateController::class, 'index'])->name('transaction-templates.index');
    Route::get('settings/quick-actions/active', [TransactionTemplateController::class, 'active'])->name('transaction-templates.active');
    Route::post('settings/quick-actions', [TransactionTemplateController::class, 'store'])->name('transaction-templates.store');
    Route::patch('settings/quick-actions/reorder', [TransactionTemplateController::class, 'reorder'])->name('transaction-templates.reorder');
    Route::patch('settings/quick-actions/{transactionTemplate:uuid}', [TransactionTemplateController::class, 'update'])->whereUuid('transactionTemplate')->name('transaction-templates.update');
    Route::patch('settings/quick-actions/{transactionTemplate:uuid}/toggle-active', [TransactionTemplateController::class, 'toggleActive'])->whereUuid('transactionTemplate')->name('transaction-templates.toggle-active');
    Route::delete('settings/quick-actions/{transactionTemplate:uuid}', [TransactionTemplateController::class, 'destroy'])->whereUuid('transactionTemplate')->name('transaction-templates.destroy');
    Route::get('settings', [ProfileController::class, 'edit'])->name('settings.index');

    // SETTINGS PROFILE
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/profile/avatar/{user:uuid}', [ProfileController::class, 'avatar'])
        ->whereUuid('user')->name('profile.avatar.show');
    Route::patch('settings/profile/notification-preferences', [ProfileController::class, 'updateNotificationPreferences'])
        ->name('settings.profile.notification-preferences.update');
    Route::post('settings/profile/push-tokens', [ProfileController::class, 'storePushToken'])
        ->middleware('feature.push-notifications')
        ->name('settings.profile.push-tokens.store');
    Route::post('settings/profile/push-tokens/status', [ProfileController::class, 'showPushDeviceStatus'])
        ->middleware('feature.push-notifications')
        ->name('settings.profile.push-tokens.status');
    Route::post('settings/profile/push-tokens/test', [ProfileController::class, 'sendPushTest'])
        ->middleware(['feature.push-notifications', 'throttle:3,1'])
        ->name('settings.profile.push-tokens.test');
    Route::delete('settings/profile/push-tokens', [ProfileController::class, 'destroyPushToken'])
        ->middleware('feature.push-notifications')
        ->name('settings.profile.push-tokens.destroy');
    Route::delete('settings/profile/sessions/{sessionId}', [ProfileController::class, 'destroySession'])
        ->name('settings.profile.sessions.destroy');
    Route::delete('settings/profile/sessions', [ProfileController::class, 'destroyOtherSessions'])
        ->name('settings.profile.sessions.destroy-others');
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
    Route::get('settings/exports', [ExportController::class, 'edit'])->name('exports.edit');
    Route::get('settings/exports/download', [ExportController::class, 'download'])->name('exports.download');
    Route::get('settings/exchange-rates', [ExchangeRateController::class, 'edit'])->name('exchange-rates.edit');
    Route::get('settings/support', [SupportRequestController::class, 'index'])->name('support.index');
    Route::post('settings/support/requests', [SupportRequestController::class, 'store'])->name('support.requests.store');
    Route::post('settings/support/requests/{supportRequest:uuid}/messages', [SupportRequestController::class, 'reply'])->whereUuid('supportRequest')->name('support.requests.reply');
    // SETTINGS BANKS
    Route::get('settings/banks', [BankController::class, 'index'])->name('banks.edit');
    Route::post('settings/banks', [BankController::class, 'store'])->name('banks.store');
    Route::patch('settings/banks/{userBank:uuid}', [BankController::class, 'update'])->whereUuid('userBank')->name('banks.update');
    Route::patch('settings/banks/{userBank:uuid}/toggle-active', [BankController::class, 'toggleActive'])
        ->whereUuid('userBank')->name('banks.toggle-active');
    Route::delete('settings/banks/{userBank:uuid}', [BankController::class, 'destroy'])->whereUuid('userBank')->name('banks.destroy');
    // SETTINGS ACCOUNTS
    Route::get('settings/accounts', [AccountController::class, 'index'])->name('accounts.edit');
    Route::post('settings/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('settings/accounts/reorder', [AccountController::class, 'reorder'])->name('accounts.reorder');
    Route::patch('settings/accounts/{account:uuid}/set-default', [AccountController::class, 'setDefault'])->whereUuid('account')->name('accounts.set-default');
    Route::patch('settings/accounts/{account:uuid}', [AccountController::class, 'update'])->whereUuid('account')->name('accounts.update');
    Route::patch('settings/accounts/{account:uuid}/toggle-active', [AccountController::class, 'toggleActive'])
        ->whereUuid('account')->name('accounts.toggle-active');
    Route::delete('settings/accounts/{account:uuid}', [AccountController::class, 'destroy'])->whereUuid('account')->name('accounts.destroy');
    // SETTINGS YEARS
    Route::get('settings/years', [UserYearController::class, 'index'])->name('years.edit');
    Route::post('settings/years', [UserYearController::class, 'store'])->name('years.store');
    Route::patch('settings/years/{userYear:uuid}', [UserYearController::class, 'update'])->whereUuid('userYear')->name('years.update');
    Route::patch('settings/years/{userYear:uuid}/activate', [UserYearController::class, 'activate'])->whereUuid('userYear')->name('years.activate');
    Route::delete('settings/years/{userYear:uuid}', [UserYearController::class, 'destroy'])->whereUuid('userYear')->name('years.destroy');
    // SETTINGS CATEGORIES
    Route::get('settings/categories', [CategoryController::class, 'index'])->name('categories.edit');
    Route::post('settings/categories/inline-effects', [PendingCategoryController::class, 'effects'])->name('categories.inline-effects');
    Route::post('settings/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('settings/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::patch('settings/categories/{category:uuid}', [CategoryController::class, 'update'])->whereUuid('category')->name('categories.update');
    Route::patch('settings/categories/{category:uuid}/toggle-active', [CategoryController::class, 'toggleActive'])
        ->whereUuid('category')->name('categories.toggle-active');
    Route::delete('settings/categories/{category:uuid}', [CategoryController::class, 'destroy'])->whereUuid('category')->name('categories.destroy');
    Route::get('settings/shared-categories', [SharedCategoryController::class, 'index'])->name('shared-categories.edit');
    Route::post('settings/shared-categories/{account:uuid}', [SharedCategoryController::class, 'store'])->whereUuid('account')->name('shared-categories.store');
    Route::post('settings/shared-categories/{account:uuid}/materialize-personal', [SharedCategoryController::class, 'materialize'])->whereUuid('account')->name('shared-categories.materialize');
    Route::patch('settings/shared-categories/{account:uuid}/{category:uuid}', [SharedCategoryController::class, 'update'])->whereUuid('category')->whereUuid('account')->name('shared-categories.update');
    Route::patch('settings/shared-categories/{account:uuid}/{category:uuid}/toggle-active', [SharedCategoryController::class, 'toggleActive'])
        ->whereUuid('category')->whereUuid('account')->name('shared-categories.toggle-active');
    Route::delete('settings/shared-categories/{account:uuid}/{category:uuid}', [SharedCategoryController::class, 'destroy'])->whereUuid('category')->whereUuid('account')->name('shared-categories.destroy');
    // SETTINGS TRACKED ITEMS
    Route::get('settings/tracked-items/movements/history', [MoveTrackedItemMovementsController::class, 'history'])->name('tracked-items.movements.history');
    Route::post('settings/tracked-items/movements/{uuid}/restore', [MoveTrackedItemMovementsController::class, 'restore'])->whereUuid('uuid')->name('tracked-items.movements.restore');
    Route::get('settings/tracked-items/movements/options', [MoveTrackedItemMovementsController::class, 'options'])->name('tracked-items.movements.options');
    Route::get('settings/tracked-items/movements/preview', [MoveTrackedItemMovementsController::class, 'preview'])->name('tracked-items.movements.preview');
    Route::post('settings/tracked-items/movements', [MoveTrackedItemMovementsController::class, 'store'])->name('tracked-items.movements.store');
    Route::get('settings/tracked-items', [TrackedItemController::class, 'index'])->name('tracked-items.edit');
    Route::post('settings/tracked-items/logo-upload', [TrackedItemLogoController::class, 'upload'])->middleware('throttle:30,1')->name('tracked-items.logo-upload');
    Route::delete('settings/tracked-items/logo-upload', [TrackedItemLogoController::class, 'discard'])->middleware('throttle:60,1')->name('tracked-items.logo-discard');
    Route::get('settings/tracked-items/logo-catalog', [TrackedItemLogoController::class, 'catalog'])->name('tracked-items.logo-catalog');
    Route::post('settings/tracked-items/logo-preview', [TrackedItemLogoController::class, 'preview'])
        ->middleware('throttle:20,1')
        ->name('tracked-items.logo-preview');
    Route::get('settings/tracked-item-logos/{websiteIdentity:uuid}', [TrackedItemLogoController::class, 'show'])
        ->whereUuid('websiteIdentity')->name('tracked-item-logos.show');
    Route::post('settings/tracked-items', [TrackedItemController::class, 'store'])->name('tracked-items.store');
    Route::post('settings/tracked-items/shared/{account:uuid}/materialize-personal', [TrackedItemController::class, 'materialize'])
        ->whereUuid('account')->name('tracked-items.materialize');
    Route::patch('settings/tracked-items/{trackedItem:uuid}', [TrackedItemController::class, 'update'])->whereUuid('trackedItem')->name('tracked-items.update');
    Route::patch('settings/tracked-items/{trackedItem:uuid}/toggle-active', [TrackedItemController::class, 'toggleActive'])
        ->whereUuid('trackedItem')->name('tracked-items.toggle-active');
    Route::delete('settings/tracked-items/{trackedItem:uuid}', [TrackedItemController::class, 'destroy'])->whereUuid('trackedItem')->name('tracked-items.destroy');
});
