<?php

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\ChangelogReleaseController;
use App\Http\Controllers\Admin\CommunicationCategoryController;
use App\Http\Controllers\Admin\CommunicationComposerController;
use App\Http\Controllers\Admin\CommunicationOutboundController;
use App\Http\Controllers\Admin\CommunicationTemplateController;
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

        Route::prefix('changelog')
            ->name('changelog.')
            ->group(function () {
                Route::get('/', [ChangelogReleaseController::class, 'index'])->name('index');
                Route::get('/create', [ChangelogReleaseController::class, 'create'])->name('create');
                Route::post('/', [ChangelogReleaseController::class, 'store'])->name('store');
                Route::get('/{changelogRelease:uuid}/edit', [ChangelogReleaseController::class, 'edit'])->name('edit');
                Route::match(['put', 'patch'], '/{changelogRelease:uuid}', [ChangelogReleaseController::class, 'update'])->name('update');
            });

        Route::prefix('communication-templates')
            ->name('communication-templates.')
            ->group(function () {
                Route::get('/', [CommunicationTemplateController::class, 'index'])->name('index');
                Route::get('/{communicationTemplate:uuid}', [CommunicationTemplateController::class, 'show'])->name('show');
                Route::get('/{communicationTemplate:uuid}/edit', [CommunicationTemplateController::class, 'edit'])->name('edit');
                Route::patch('/{communicationTemplate:uuid}/global-override', [CommunicationTemplateController::class, 'updateGlobalOverride'])->name('global-override.update');
                Route::post('/{communicationTemplate:uuid}/global-override/disable', [CommunicationTemplateController::class, 'disableGlobalOverride'])->name('global-override.disable');
            });

        Route::prefix('communication-categories')
            ->name('communication-categories.')
            ->group(function () {
                Route::get('/', [CommunicationCategoryController::class, 'index'])->name('index');
                Route::get('/{communicationCategory:uuid}', [CommunicationCategoryController::class, 'show'])->name('show');
                Route::patch('/{communicationCategory:uuid}/channels', [CommunicationCategoryController::class, 'updateChannels'])->name('channels.update');
            });

        Route::prefix('communications/compose')
            ->name('communications.compose.')
            ->group(function () {
                Route::get('/', [CommunicationComposerController::class, 'index'])->name('index');
                Route::get('/recipients', [CommunicationComposerController::class, 'recipients'])->name('recipients');
                Route::post('/preview', [CommunicationComposerController::class, 'preview'])->name('preview');
                Route::post('/send', [CommunicationComposerController::class, 'send'])->name('send');
            });

        Route::prefix('communications/outbound')
            ->name('communications.outbound.')
            ->group(function () {
                Route::get('/', [CommunicationOutboundController::class, 'index'])->name('index');
                Route::get('/{outboundMessage:uuid}', [CommunicationOutboundController::class, 'show'])->name('show');
            });
    });
