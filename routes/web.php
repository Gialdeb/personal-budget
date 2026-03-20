<?php

use App\Http\Controllers\BudgetPlanningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

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
    Route::get('budget-planning', [BudgetPlanningController::class, 'index'])->name('budget-planning');
    Route::get('budget-planning/data', [BudgetPlanningController::class, 'index'])->name('budget-planning.data');
    Route::patch('budget-planning/cell', [BudgetPlanningController::class, 'updateCell'])
        ->name('budget-planning.update-cell');
    Route::post('budget-planning/copy-previous-year', [BudgetPlanningController::class, 'copyPreviousYear'])
        ->name('budget-planning.copy-previous-year');
});

require __DIR__.'/settings.php';
