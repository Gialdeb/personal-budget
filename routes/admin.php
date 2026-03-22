<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // ADMIN IMPERSONATE
        Route::impersonate();

        // ADMIN DASHBOARD
        Route::get('/', fn () => inertia('admin/Index'))->name('index');

        // ADMIN USERS
        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::patch('/users/{user}/ban', [UserStatusController::class, 'ban'])->name('users.ban');
        Route::patch('/users/{user}/suspend', [UserStatusController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/reactivate', [UserStatusController::class, 'reactivate'])->name('users.reactivate');
        Route::patch('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');

        // ADMIN ACTIVITY LOGS
        Route::get('/activity-log', fn () => inertia('admin/ActivityLog'))->name('activity-log');
    });
