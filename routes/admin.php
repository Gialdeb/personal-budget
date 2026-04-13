<?php

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\ChangelogReleaseController;
use App\Http\Controllers\Admin\CommunicationCategoryController;
use App\Http\Controllers\Admin\CommunicationComposerController;
use App\Http\Controllers\Admin\CommunicationOutboundController;
use App\Http\Controllers\Admin\CommunicationTemplateController;
use App\Http\Controllers\Admin\ContextualHelpEntryController;
use App\Http\Controllers\Admin\KnowledgeArticleController;
use App\Http\Controllers\Admin\KnowledgeSectionController;
use App\Http\Controllers\Admin\RichContentAssetController;
use App\Http\Controllers\Admin\SupportRequestController;
use App\Http\Controllers\Admin\UserBillingController;
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
        Route::get('/users/{user:uuid}/billing', [UserBillingController::class, 'show'])
            ->whereUuid('user')
            ->name('users.billing.show');
        Route::post('/users/{user:uuid}/billing/transactions', [UserBillingController::class, 'storeTransaction'])
            ->whereUuid('user')
            ->name('users.billing.transactions.store');
        Route::match(['patch', 'post'], '/users/{user:uuid}/billing/transactions/{billingTransaction}', [UserBillingController::class, 'updateTransaction'])
            ->whereUuid('user')
            ->name('users.billing.transactions.update');
        Route::match(['patch', 'post'], '/users/{user:uuid}/billing/transactions/{billingTransaction}/assign', [UserBillingController::class, 'assignTransaction'])
            ->whereUuid('user')
            ->name('users.billing.transactions.assign');
        Route::match(['patch', 'post'], '/users/{user:uuid}/billing/subscription', [UserBillingController::class, 'updateSubscription'])
            ->whereUuid('user')
            ->name('users.billing.subscription.update');
        Route::delete('/users/{user:uuid}/billing/subscription', [UserBillingController::class, 'destroySubscription'])
            ->whereUuid('user')
            ->name('users.billing.subscription.destroy');
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

        Route::prefix('knowledge-sections')
            ->name('knowledge-sections.')
            ->group(function () {
                Route::get('/', [KnowledgeSectionController::class, 'index'])->name('index');
                Route::get('/create', [KnowledgeSectionController::class, 'create'])->name('create');
                Route::post('/', [KnowledgeSectionController::class, 'store'])->name('store');
                Route::get('/{knowledgeSection:uuid}/edit', [KnowledgeSectionController::class, 'edit'])->name('edit');
                Route::match(['put', 'patch'], '/{knowledgeSection:uuid}', [KnowledgeSectionController::class, 'update'])->name('update');
                Route::delete('/{knowledgeSection:uuid}', [KnowledgeSectionController::class, 'destroy'])->name('destroy');
            });

        Route::prefix('knowledge-articles')
            ->name('knowledge-articles.')
            ->group(function () {
                Route::get('/', [KnowledgeArticleController::class, 'index'])->name('index');
                Route::get('/create', [KnowledgeArticleController::class, 'create'])->name('create');
                Route::post('/', [KnowledgeArticleController::class, 'store'])->name('store');
                Route::get('/{knowledgeArticle:uuid}/edit', [KnowledgeArticleController::class, 'edit'])->name('edit');
                Route::match(['put', 'patch'], '/{knowledgeArticle:uuid}', [KnowledgeArticleController::class, 'update'])->name('update');
                Route::delete('/{knowledgeArticle:uuid}', [KnowledgeArticleController::class, 'destroy'])->name('destroy');
            });

        Route::prefix('support-requests')
            ->name('support-requests.')
            ->group(function () {
                Route::get('/', [SupportRequestController::class, 'index'])->name('index');
                Route::get('/{supportRequest:uuid}', [SupportRequestController::class, 'show'])->name('show');
                Route::match(['put', 'patch'], '/{supportRequest:uuid}', [SupportRequestController::class, 'update'])->name('update');
            });

        Route::prefix('contextual-help')
            ->name('contextual-help.')
            ->group(function () {
                Route::get('/', [ContextualHelpEntryController::class, 'index'])->name('index');
                Route::get('/create', [ContextualHelpEntryController::class, 'create'])->name('create');
                Route::post('/', [ContextualHelpEntryController::class, 'store'])->name('store');
                Route::get('/{contextualHelpEntry:uuid}/edit', [ContextualHelpEntryController::class, 'edit'])->name('edit');
                Route::match(['put', 'patch'], '/{contextualHelpEntry:uuid}', [ContextualHelpEntryController::class, 'update'])->name('update');
            });

        Route::prefix('rich-content-assets')
            ->name('rich-content-assets.')
            ->group(function () {
                Route::post('/', [RichContentAssetController::class, 'store'])->name('store');
                Route::delete('/', [RichContentAssetController::class, 'destroy'])->name('destroy');
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
