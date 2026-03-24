<?php

use App\Http\Controllers\BudgetPlanningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RecurringEntryController;
use App\Http\Controllers\RecurringEntryOccurrenceController;
use App\Http\Controllers\RecurringEntryTransactionController;
use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// PUBLIC ROUTE
Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');
// LANGUAGE PATH
Route::patch('/settings/locale', [LocaleController::class, 'update'])
    ->name('settings.locale.update');

Route::middleware(['auth', 'verified', 'not_banned', 'role:admin|user'])->group(function () {
    // DASHBOARD
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/data', [DashboardController::class, 'index'])->name('dashboard.data');
});

Route::middleware(['auth', 'verified', 'not_banned', 'role:admin|user'])->group(function () {
    // TRANSACTIONS
    Route::get('transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{year}/{month}', [TransactionsController::class, 'show'])
        ->whereNumber('year')
        ->whereNumber('month')
        ->name('transactions.show');
    Route::post('transactions/{year}/{month}', [TransactionsController::class, 'store'])
        ->whereNumber('year')
        ->whereNumber('month')
        ->name('transactions.store');
    Route::patch('transactions/{year}/{month}/{transaction:uuid}', [TransactionsController::class, 'update'])
        ->whereNumber('year')
        ->whereNumber('month')
        ->name('transactions.update');
    Route::delete('transactions/{year}/{month}/{transaction:uuid}', [TransactionsController::class, 'destroy'])
        ->whereNumber('year')
        ->whereNumber('month')
        ->name('transactions.destroy');
    Route::patch('transactions/{year}/{month}/{transactionUuid}/restore', [TransactionsController::class, 'restore'])
        ->whereNumber('year')
        ->whereNumber('month')
        ->name('transactions.restore');
    Route::delete('transactions/{year}/{month}/{transactionUuid}/force', [TransactionsController::class, 'forceDestroy'])
        ->whereNumber('year')
        ->whereNumber('month')
        ->name('transactions.force-destroy');
    // RECURRING ENTRIES
    Route::get('recurring-entries', [RecurringEntryController::class, 'index'])->name('recurring-entries.index');
    Route::post('recurring-entries', [RecurringEntryController::class, 'store'])->name('recurring-entries.store');
    Route::get('recurring-entries/{recurringEntry:uuid}', [RecurringEntryController::class, 'show'])->name('recurring-entries.show');
    Route::patch('recurring-entries/{recurringEntry:uuid}', [RecurringEntryController::class, 'update'])->name('recurring-entries.update');
    Route::patch('recurring-entries/{recurringEntry:uuid}/pause', [RecurringEntryController::class, 'pause'])->name('recurring-entries.pause');
    Route::patch('recurring-entries/{recurringEntry:uuid}/resume', [RecurringEntryController::class, 'resume'])->name('recurring-entries.resume');
    Route::patch('recurring-entries/{recurringEntry:uuid}/cancel', [RecurringEntryController::class, 'cancel'])->name('recurring-entries.cancel');
    Route::post('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/convert', [RecurringEntryOccurrenceController::class, 'convert'])->name('recurring-entries.occurrences.convert');
    Route::delete('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/conversion', [RecurringEntryOccurrenceController::class, 'undoConversion'])->name('recurring-entries.occurrences.undo-conversion');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/skip', [RecurringEntryOccurrenceController::class, 'skip'])->name('recurring-entries.occurrences.skip');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/cancel', [RecurringEntryOccurrenceController::class, 'cancel'])->name('recurring-entries.occurrences.cancel');
    Route::post('recurring-transactions/{transaction:uuid}/refund', [RecurringEntryTransactionController::class, 'refund'])->name('recurring-transactions.refund');
    // IMPORTS
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('imports/template/csv', [ImportController::class, 'downloadTemplate'])->name('imports.template');
    Route::get('imports/{import:uuid}', [ImportController::class, 'show'])->name('imports.show');
    Route::delete('imports/{import:uuid}', [ImportController::class, 'destroy'])->name('imports.destroy');
    Route::post('imports/{import:uuid}/import-ready', [ImportController::class, 'importReady'])->name('imports.import-ready');
    Route::post('imports/{import:uuid}/rollback', [ImportController::class, 'rollback'])->name('imports.rollback');
    Route::patch('/imports/{import:uuid}/rows/{row:uuid}/review', [ImportController::class, 'updateRowReview'])
        ->name('imports.rows.update-review');
    Route::post('/imports/{import:uuid}/rows/{row:uuid}/skip', [ImportController::class, 'skipRow'])
        ->name('imports.rows.skip');
    Route::post('/imports/{import:uuid}/rows/{row:uuid}/approve-duplicate', [ImportController::class, 'approveDuplicateRow'])
        ->name('imports.rows.approve-duplicate');
    // BUDGET PLANNING
    Route::get('budget-planning', [BudgetPlanningController::class, 'index'])->name('budget-planning');
    Route::get('budget-planning/data', [BudgetPlanningController::class, 'index'])->name('budget-planning.data');
    Route::patch('budget-planning/cell', [BudgetPlanningController::class, 'updateCell'])
        ->name('budget-planning.update-cell');
    Route::post('budget-planning/copy-previous-year', [BudgetPlanningController::class, 'copyPreviousYear'])
        ->name('budget-planning.copy-previous-year');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
