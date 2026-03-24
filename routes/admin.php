<?php

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Controllers\ImpersonateController;

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
    });

Route::middleware(['auth', 'verified', 'not_banned', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // ADMIN IMPERSONATE
        Route::get('/impersonate/take/{id}/{guardName?}', [ImpersonateController::class, 'take'])->name('impersonate');

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

        // ADMIN AUTOMATION JOBS
        Route::prefix('automation')
            ->name('automation.')
            ->group(function () {
                Route::get('/', [AutomationController::class, 'index'])->name('index');
                Route::get('/runs/{automationRun}', [AutomationController::class, 'show'])->name('show');
                Route::post('/pipelines/{pipeline}/run', [AutomationController::class, 'run'])->name('run');
                Route::post('/runs/{automationRun}/retry', [AutomationController::class, 'retry'])->name('retry');
            });
    });
