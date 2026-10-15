<?php

use App\Http\Controllers\AutomaticPaymentVerificationController;
use App\Http\Controllers\BudgetPlanningController;
use App\Http\Controllers\CounterpartyPaymentController;
use App\Http\Controllers\CreditDebtController;
use App\Http\Controllers\CreditDebtPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntrySearchController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MonthlyRecapController;
use App\Http\Controllers\NotificationInboxController;
use App\Http\Controllers\RecurringEntryController;
use App\Http\Controllers\RecurringEntryOccurrenceController;
use App\Http\Controllers\RecurringEntryTransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Reports\AccountReportExportController;
use App\Http\Controllers\SalaryEmploymentController;
use App\Http\Controllers\SessionActivityController;
use App\Http\Controllers\SharedExpenseController;
use App\Http\Controllers\SharedExpenseSettlementController;
use App\Http\Controllers\Sharing\AccountSharingController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'not_banned', 'role:admin|user'])->group(function () {
    Route::get('counterparties/{relation:uuid}', [CounterpartyPaymentController::class, 'show'])->whereUuid('relation')->name('counterparties.show');
    Route::post('counterparties/{relation:uuid}/activate', [CounterpartyPaymentController::class, 'activate'])->whereUuid('relation')->name('counterparties.activate');
    Route::post('counterparties/{relation:uuid}/payments', [CounterpartyPaymentController::class, 'store'])->whereUuid('relation')->name('counterparties.payments.store');
    Route::patch('counterparties/{relation:uuid}/payments/{payment:uuid}', [CounterpartyPaymentController::class, 'update'])->whereUuid('relation')->whereUuid('payment')->name('counterparties.payments.update');
    Route::patch('counterparties/{relation:uuid}/payments/{payment:uuid}/allocations', [CounterpartyPaymentController::class, 'allocations'])->whereUuid('relation')->whereUuid('payment')->name('counterparties.payments.allocations');
    Route::delete('counterparties/{relation:uuid}/payments/{payment:uuid}', [CounterpartyPaymentController::class, 'destroy'])->whereUuid('relation')->whereUuid('payment')->name('counterparties.payments.destroy');
    Route::post('counterparties/{relation:uuid}/payments/{payment:uuid}/returns', [CounterpartyPaymentController::class, 'returnMoney'])->whereUuid('relation')->whereUuid('payment')->name('counterparties.payments.returns');
    Route::post('counterparties/{relation:uuid}/compensations', [CounterpartyPaymentController::class, 'compensate'])->whereUuid('relation')->name('counterparties.compensations.store');
    Route::post('counterparties/{relation:uuid}/compensations/{entry:uuid}/reverse', [CounterpartyPaymentController::class, 'reverseCompensation'])->whereUuid('relation')->whereUuid('entry')->name('counterparties.compensations.reverse');
    // DASHBOARD
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/data', [DashboardController::class, 'index'])->name('dashboard.data');
    Route::get('dashboard/analysis', [DashboardController::class, 'analysis'])->name('dashboard.analysis');
    if (app()->isLocal()) {
        Route::get('dashboard/legacy', [DashboardController::class, 'legacy'])->name('dashboard.legacy');
    }
    Route::get('dashboard/monthly-recap/{year}/{month}', [MonthlyRecapController::class, 'show'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->name('monthly-recap.show');
    Route::get('dashboard/monthly-recap/{year}/{month}/pdf', [MonthlyRecapController::class, 'pdf'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->name('monthly-recap.pdf');
    Route::get('support', fn (Request $request) => redirect()->route('support.index', $request->query()));
    Route::post('support/requests', [SupportRequestController::class, 'store']);
    Route::get('entry-search', [EntrySearchController::class, 'index'])->name('entry-search.index');
    Route::delete('shared-expenses/ledger/{ledger:uuid}', [SharedExpenseController::class, 'destroyLedger'])->whereUuid('ledger')->name('shared-expenses.ledger.destroy');
    Route::post('shared-expenses/{counterparty:uuid}/prepare-payments', [SharedExpenseController::class, 'preparePayments'])->whereUuid('counterparty')->name('shared-expenses.payments.prepare');
    Route::patch('shared-expenses/references/{kind}/{uuid}', [SharedExpenseController::class, 'resolveReference'])->whereIn('kind', ['transaction', 'ledger'])->whereUuid('uuid')->name('shared-expenses.references.resolve');
    Route::get('shared-expenses', [SharedExpenseController::class, 'index'])->name('shared-expenses.index');
    Route::post('shared-expenses/counterparties', [SharedExpenseController::class, 'storeCounterparty'])->name('shared-expenses.counterparties.store');
    Route::post('shared-expenses/{counterparty}/settlements', [SharedExpenseSettlementController::class, 'store'])->whereUuid('counterparty')->name('shared-expenses.settlements.store');
    Route::patch('shared-expenses/settlements/{settlement}', [SharedExpenseSettlementController::class, 'update'])->whereUuid('settlement')->name('shared-expenses.settlements.update');
    Route::delete('shared-expenses/settlements/{settlement}', [SharedExpenseSettlementController::class, 'destroy'])->whereUuid('settlement')->name('shared-expenses.settlements.destroy');
    Route::get('notifications', [NotificationInboxController::class, 'index'])->name('notifications.index');
    Route::get('notifications/preview', [NotificationInboxController::class, 'preview'])->name('notifications.preview');
    Route::post('notifications/mark-all-read', [NotificationInboxController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markAsRead'])->whereUuid('notification')->name('notifications.mark-as-read');
    Route::delete('notifications/{notification}', [NotificationInboxController::class, 'destroy'])->whereUuid('notification')->name('notifications.destroy');
    Route::get('session/status', [SessionActivityController::class, 'status'])->name('session.status');
    Route::post('session/warning', [SessionActivityController::class, 'triggerWarning'])->name('session.warning.trigger');
    Route::post('session/keep-alive', [SessionActivityController::class, 'keepAlive'])->name('session.keep-alive');
});

Route::middleware(['auth', 'verified', 'not_banned', 'role:admin|user'])->group(function () {
    // REPORTS
    Route::middleware('feature.reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::get('reports/kpis', [ReportController::class, 'kpis'])->middleware('feature.reports:kpis')->name('reports.kpis');
        Route::get('reports/trend', [ReportController::class, 'trend'])->middleware('feature.reports:kpis')->name('reports.trend');
        Route::get('reports/categories', [ReportController::class, 'categories'])->middleware('feature.reports:categories')->name('reports.categories');
        Route::get('reports/category-analysis', [ReportController::class, 'categoryAnalysis'])->middleware('feature.reports:category_analysis')->name('reports.category-analysis');
        Route::get('reports/category-analysis/export', [ReportController::class, 'exportCategoryAnalysis'])->middleware('feature.reports:category_analysis')->name('reports.category-analysis.export');
        Route::get('reports/category-analysis/export/pdf', [ReportController::class, 'exportCategoryAnalysisPdf'])->middleware('feature.reports:category_analysis')->name('reports.category-analysis.export-pdf');
        Route::get('reports/accounts', [ReportController::class, 'accounts'])->middleware('feature.reports:accounts')->name('reports.accounts');
        Route::get('reports/accounts/export', AccountReportExportController::class)->middleware('feature.reports:accounts')->name('reports.accounts.export');
    });

    // CREDITS / DEBTS
    Route::middleware('feature.credits-debts')->group(function () {
        Route::get('credits-debts', [CreditDebtController::class, 'index'])->name('credits-debts.index');
        Route::post('credits-debts', [CreditDebtController::class, 'store'])->name('credits-debts.store');
        Route::get('credits-debts/{creditDebtItem:uuid}', [CreditDebtController::class, 'show'])->whereUuid('creditDebtItem')->name('credits-debts.show');
        Route::put('credits-debts/{creditDebtItem:uuid}', [CreditDebtController::class, 'update'])->whereUuid('creditDebtItem')->name('credits-debts.update');
        Route::delete('credits-debts/{creditDebtItem:uuid}', [CreditDebtController::class, 'destroy'])->whereUuid('creditDebtItem')->name('credits-debts.destroy');
        Route::post('credits-debts/{creditDebtItem:uuid}/payments', [CreditDebtPaymentController::class, 'store'])->whereUuid('creditDebtItem')->name('credits-debts.payments.store');
        Route::delete('credits-debts/{creditDebtItem:uuid}/payments/{payment:uuid}', [CreditDebtPaymentController::class, 'destroy'])->whereUuid('creditDebtItem')->whereUuid('payment')->name('credits-debts.payments.destroy');
    });

    // TRANSACTIONS
    Route::post('transactions/{transaction:uuid}/payment-confirmation', [AutomaticPaymentVerificationController::class, 'confirm'])->whereUuid('transaction')->name('transactions.payment-confirmation');
    Route::post('transactions/{transaction:uuid}/payment-problem', [AutomaticPaymentVerificationController::class, 'problem'])->whereUuid('transaction')->name('transactions.payment-problem');
    Route::get('transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{year}/{month}', [TransactionsController::class, 'show'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->name('transactions.show');
    Route::post('transactions/{year}/{month}', [TransactionsController::class, 'store'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->name('transactions.store');
    Route::post('transactions/{year}/{month}/balance-adjustment-preview', [TransactionsController::class, 'previewBalanceAdjustment'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->name('transactions.balance-adjustment-preview');
    Route::post('transactions/{year}/{month}/exchange-preview', [TransactionsController::class, 'previewExchangeSnapshot'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->name('transactions.exchange-preview');
    Route::post('transactions/tracked-items', [TransactionsController::class, 'storeTrackedItemOption'])
        ->name('transactions.tracked-items.store');
    Route::patch('transactions/{year}/{month}/shared-ledger/{ledger:uuid}', [TransactionsController::class, 'updateSharedLedger'])
        ->where('year', '[1-9][0-9]{0,3}')->where('month', '0?[1-9]|1[0-2]')->whereUuid('ledger')->name('transactions.shared-ledger.update');
    Route::patch('transactions/{year}/{month}/{transaction:uuid}', [TransactionsController::class, 'update'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->whereUuid('transaction')->name('transactions.update');
    Route::post('transactions/{year}/{month}/{transaction:uuid}/refund', [TransactionsController::class, 'refund'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->whereUuid('transaction')->name('transactions.refund');
    Route::delete('transactions/{year}/{month}/{transaction:uuid}/refund', [TransactionsController::class, 'undoRefund'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->whereUuid('transaction')->name('transactions.undo-refund');
    Route::delete('transactions/{year}/{month}/{transaction:uuid}', [TransactionsController::class, 'destroy'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->whereUuid('transaction')->name('transactions.destroy');
    Route::patch('transactions/{year}/{month}/{transactionUuid}/restore', [TransactionsController::class, 'restore'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->whereUuid('transactionUuid')->name('transactions.restore');
    Route::delete('transactions/{year}/{month}/{transactionUuid}/force', [TransactionsController::class, 'forceDestroy'])
        ->where('year', '[1-9][0-9]{0,3}')
        ->where('month', '0?[1-9]|1[0-2]')
        ->whereUuid('transactionUuid')->name('transactions.force-destroy');
    // RECURRING ENTRIES
    Route::get('salary-employments/{salaryEmployment:uuid}', [SalaryEmploymentController::class, 'show'])->whereUuid('salaryEmployment')->name('salary-employments.show');
    Route::patch('salary-employments/{salaryEmployment:uuid}', [SalaryEmploymentController::class, 'update'])->whereUuid('salaryEmployment')->name('salary-employments.update');
    Route::post('salary-employments/{salaryEmployment:uuid}/occurrences/{detail}/explain', [SalaryEmploymentController::class, 'explain'])->whereUuid('salaryEmployment')->whereNumber('detail')->name('salary-employments.occurrences.explain');
    Route::patch('salary-employments/{salaryEmployment:uuid}/end', [SalaryEmploymentController::class, 'end'])->whereUuid('salaryEmployment')->name('salary-employments.end');
    Route::patch('salary-employments/{salaryEmployment:uuid}/occurrences/{detail}', [SalaryEmploymentController::class, 'updateOccurrence'])->whereUuid('salaryEmployment')->whereNumber('detail')->name('salary-employments.occurrences.update');
    Route::get('recurring-entries', [RecurringEntryController::class, 'index'])->name('recurring-entries.index');
    Route::post('recurring-entries', [RecurringEntryController::class, 'store'])->name('recurring-entries.store');
    Route::post('recurring-entries/exchange-preview', [RecurringEntryController::class, 'previewExchangeSnapshot'])
        ->name('recurring-entries.exchange-preview');
    Route::post('recurring-entries/tracked-items', [RecurringEntryController::class, 'storeTrackedItemOption'])
        ->name('recurring-entries.tracked-items.store');
    Route::get('recurring-entries/{recurringEntry:uuid}', [RecurringEntryController::class, 'show'])->whereUuid('recurringEntry')->name('recurring-entries.show');
    Route::patch('recurring-entries/{recurringEntry:uuid}', [RecurringEntryController::class, 'update'])->whereUuid('recurringEntry')->name('recurring-entries.update');
    Route::patch('recurring-entries/{recurringEntry:uuid}/installment-distribution', [RecurringEntryController::class, 'updateInstallmentDistribution'])->whereUuid('recurringEntry')->name('recurring-entries.installment-distribution.update');
    Route::delete('recurring-entries/{recurringEntry:uuid}', [RecurringEntryController::class, 'destroy'])->whereUuid('recurringEntry')->name('recurring-entries.destroy');
    Route::patch('recurring-entries/{recurringEntry:uuid}/pause', [RecurringEntryController::class, 'pause'])->whereUuid('recurringEntry')->name('recurring-entries.pause');
    Route::patch('recurring-entries/{recurringEntry:uuid}/resume', [RecurringEntryController::class, 'resume'])->whereUuid('recurringEntry')->name('recurring-entries.resume');
    Route::patch('recurring-entries/{recurringEntry:uuid}/cancel', [RecurringEntryController::class, 'cancel'])->whereUuid('recurringEntry')->name('recurring-entries.cancel');
    Route::post('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/convert', [RecurringEntryOccurrenceController::class, 'convert'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.convert');
    Route::get('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/payment-candidates', [RecurringEntryOccurrenceController::class, 'paymentCandidates'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.payment-candidates');
    Route::post('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/payments', [RecurringEntryOccurrenceController::class, 'allocatePayment'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.payments.store');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/amount', [RecurringEntryOccurrenceController::class, 'updateAmount'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.amount.update');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/override', [RecurringEntryOccurrenceController::class, 'update'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.override.update');
    Route::delete('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/conversion', [RecurringEntryOccurrenceController::class, 'undoConversion'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.undo-conversion');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/skip', [RecurringEntryOccurrenceController::class, 'skip'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.skip');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/restore', [RecurringEntryOccurrenceController::class, 'restore'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.restore');
    Route::patch('recurring-entries/{recurringEntry:uuid}/occurrences/{occurrence:uuid}/cancel', [RecurringEntryOccurrenceController::class, 'cancel'])->whereUuid('occurrence')->whereUuid('recurringEntry')->name('recurring-entries.occurrences.cancel');
    Route::post('recurring-transactions/{transaction:uuid}/refund', [RecurringEntryTransactionController::class, 'refund'])->whereUuid('transaction')->name('recurring-transactions.refund');
    // IMPORTS
    Route::prefix('settings/imports')->middleware('feature.imports')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/', [ImportController::class, 'store'])->name('imports.store');
        Route::get('template/xlsx', [ImportController::class, 'downloadTemplate'])->name('imports.template');
        Route::get('{import:uuid}', [ImportController::class, 'show'])->withTrashed()->whereUuid('import')->name('imports.show');
        Route::post('{import:uuid}/archive', [ImportController::class, 'archive'])->whereUuid('import')->name('imports.archive');
        Route::post('{import}/restore', [ImportController::class, 'restore'])->whereUuid('import')->name('imports.restore');
        Route::delete('{import:uuid}', [ImportController::class, 'destroy'])->whereUuid('import')->name('imports.destroy');
        Route::post('{import:uuid}/import-ready', [ImportController::class, 'importReady'])->whereUuid('import')->name('imports.import-ready');
        Route::post('{import:uuid}/rollback', [ImportController::class, 'rollback'])->whereUuid('import')->name('imports.rollback');
        Route::patch('{import:uuid}/rows/{row:uuid}/review', [ImportController::class, 'updateRowReview'])
            ->whereUuid('row')->whereUuid('import')->name('imports.rows.update-review');
        Route::post('{import:uuid}/rows/{row:uuid}/skip', [ImportController::class, 'skipRow'])
            ->whereUuid('row')->whereUuid('import')->name('imports.rows.skip');
        Route::post('{import:uuid}/rows/{row:uuid}/approve-duplicate', [ImportController::class, 'approveDuplicateRow'])
            ->whereUuid('row')->whereUuid('import')->name('imports.rows.approve-duplicate');
    });
    // BUDGET PLANNING
    Route::get('budget-planning', [BudgetPlanningController::class, 'index'])->name('budget-planning');
    Route::get('budget-planning/data', [BudgetPlanningController::class, 'index'])->name('budget-planning.data');
    Route::patch('budget-planning/cell', [BudgetPlanningController::class, 'updateCell'])
        ->name('budget-planning.update-cell');
    Route::post('budget-planning/copy-previous-year', [BudgetPlanningController::class, 'copyPreviousYear'])
        ->name('budget-planning.copy-previous-year');
    // SHARING ACCOUNTS
    Route::prefix('sharing')->name('sharing.')->group(function () {
        Route::get('/accounts/{account}/members', [AccountSharingController::class, 'members'])
            ->whereUuid('account')->name('accounts.members');
        Route::get('/accounts/{account}/invitations', [AccountSharingController::class, 'invitations'])
            ->whereUuid('account')->name('accounts.invitations');
        Route::post('/accounts/{account}/invitations', [AccountSharingController::class, 'invite'])
            ->whereUuid('account')->name('accounts.invitations.store');
        Route::post('/account-invitations/{accountInvitation}/accept', [AccountSharingController::class, 'accept'])
            ->whereUuid('accountInvitation')->name('account-invitations.accept');
        Route::post('/account-memberships/{accountMembership}/leave', [AccountSharingController::class, 'leave'])
            ->whereUuid('accountMembership')->name('account-memberships.leave');
        Route::patch('/account-memberships/{accountMembership}/role', [AccountSharingController::class, 'updateRole'])
            ->whereUuid('accountMembership')->name('account-memberships.update-role');
        Route::post('/account-memberships/{accountMembership}/revoke', [AccountSharingController::class, 'revoke'])
            ->whereUuid('accountMembership')->name('account-memberships.revoke');
        Route::post('/account-memberships/{accountMembership}/restore', [AccountSharingController::class, 'restore'])
            ->whereUuid('accountMembership')->name('account-memberships.restore');
    });
});
