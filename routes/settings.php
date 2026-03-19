<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\CategoryController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
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
    Route::get('settings/accounts', [AccountController::class, 'index'])->name('accounts.edit');
    Route::post('settings/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('settings/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::patch('settings/accounts/{account}/toggle-active', [AccountController::class, 'toggleActive'])
        ->name('accounts.toggle-active');
    Route::delete('settings/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::get('settings/years', [UserYearController::class, 'index'])->name('years.edit');
    Route::post('settings/years', [UserYearController::class, 'store'])->name('years.store');
    Route::patch('settings/years/{userYear}', [UserYearController::class, 'update'])->name('years.update');
    Route::patch('settings/years/{userYear}/activate', [UserYearController::class, 'activate'])->name('years.activate');
    Route::delete('settings/years/{userYear}', [UserYearController::class, 'destroy'])->name('years.destroy');
    Route::get('settings/categories', [CategoryController::class, 'index'])->name('categories.edit');
    Route::post('settings/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('settings/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('settings/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive'])
        ->name('categories.toggle-active');
    Route::delete('settings/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});
