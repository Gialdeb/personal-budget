<?php

use App\Http\Controllers\BudgetPlanningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::patch('/settings/locale', [LocaleController::class, 'update'])
    ->name('settings.locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/data', [DashboardController::class, 'index'])->name('dashboard.data');
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
    Route::get('budget-planning', [BudgetPlanningController::class, 'index'])->name('budget-planning');
    Route::get('budget-planning/data', [BudgetPlanningController::class, 'index'])->name('budget-planning.data');
    Route::patch('budget-planning/cell', [BudgetPlanningController::class, 'updateCell'])
        ->name('budget-planning.update-cell');
    Route::post('budget-planning/copy-previous-year', [BudgetPlanningController::class, 'copyPreviousYear'])
        ->name('budget-planning.copy-previous-year');
});

require __DIR__.'/settings.php';
