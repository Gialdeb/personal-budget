<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\BankController;
use App\Http\Controllers\Settings\CategoryController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TrackedItemController;
use App\Http\Controllers\Settings\UserYearController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    Route::get('settings/banks', [BankController::class, 'index'])->name('banks.edit');
    Route::post('settings/banks', [BankController::class, 'store'])->name('banks.store');
    Route::patch('settings/banks/{userBank:uuid}', [BankController::class, 'update'])->name('banks.update');
    Route::patch('settings/banks/{userBank:uuid}/toggle-active', [BankController::class, 'toggleActive'])
        ->name('banks.toggle-active');
    Route::delete('settings/banks/{userBank:uuid}', [BankController::class, 'destroy'])->name('banks.destroy');
    Route::get('settings/accounts', [AccountController::class, 'index'])->name('accounts.edit');
    Route::post('settings/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('settings/accounts/{account:uuid}', [AccountController::class, 'update'])->name('accounts.update');
    Route::patch('settings/accounts/{account:uuid}/toggle-active', [AccountController::class, 'toggleActive'])
        ->name('accounts.toggle-active');
    Route::delete('settings/accounts/{account:uuid}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::get('settings/years', [UserYearController::class, 'index'])->name('years.edit');
    Route::post('settings/years', [UserYearController::class, 'store'])->name('years.store');
    Route::patch('settings/years/{userYear:uuid}', [UserYearController::class, 'update'])->name('years.update');
    Route::patch('settings/years/{userYear:uuid}/activate', [UserYearController::class, 'activate'])->name('years.activate');
    Route::delete('settings/years/{userYear:uuid}', [UserYearController::class, 'destroy'])->name('years.destroy');
    Route::get('settings/categories', [CategoryController::class, 'index'])->name('categories.edit');
    Route::post('settings/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('settings/categories/{category:uuid}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('settings/categories/{category:uuid}/toggle-active', [CategoryController::class, 'toggleActive'])
        ->name('categories.toggle-active');
    Route::delete('settings/categories/{category:uuid}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('settings/tracked-items', [TrackedItemController::class, 'index'])->name('tracked-items.edit');
    Route::post('settings/tracked-items', [TrackedItemController::class, 'store'])->name('tracked-items.store');
    Route::patch('settings/tracked-items/{trackedItem:uuid}', [TrackedItemController::class, 'update'])->name('tracked-items.update');
    Route::patch('settings/tracked-items/{trackedItem:uuid}/toggle-active', [TrackedItemController::class, 'toggleActive'])
        ->name('tracked-items.toggle-active');
    Route::delete('settings/tracked-items/{trackedItem:uuid}', [TrackedItemController::class, 'destroy'])->name('tracked-items.destroy');
});
