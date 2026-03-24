<?php

namespace App\Http\Controllers;

use App\Enums\TransactionKindEnum;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Transactions\TransactionMutationService;
use App\Services\Transactions\TransactionNavigationService;
use App\Services\UserYearService;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    public function __construct(
        protected ManagementContextResolver $managementContextResolver,
        protected TransactionNavigationService $transactionNavigationService,
        protected MonthlyTransactionSheetService $monthlyTransactionSheetService,
        protected TransactionMutationService $transactionMutationService,
        protected UserYearService $userYearService
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        ['year' => $year] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $this->transactionNavigationService->resolveLandingMonth(
            $user,
            $year,
            session('dashboard_month')
        );

        return redirect()->route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function show(Request $request): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveTransactions($request, $user);

        if ((int) $request->route('year') !== $year || (int) $request->route('month') !== $month) {
            return redirect()->route('transactions.show', [
                'year' => $year,
                'month' => $month,
            ]);
        }

        $this->managementContextResolver->persist($user, $year, $month);

        $data = $this->monthlyTransactionSheetService->build($user, $year, $month);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('transactions/Show', $this->showPayload($data, $year, $month));
    }

    public function store(StoreTransactionRequest $request, int $year, int $month): RedirectResponse
    {
        $this->transactionMutationService->store(
            $request->user(),
            $request->validated()
        );

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.created'));
    }

    public function update(
        UpdateTransactionRequest $request,
        int $year,
        int $month,
        Transaction $transaction
    ): RedirectResponse {
        $transaction = $this->ownedTransaction($request, $transaction, $year, $month);

        if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.opening_balance.mutation_locked'),
            ]);
        }

        $this->transactionMutationService->update(
            $request->user(),
            $transaction,
            $request->validated()
        );

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.updated'));
    }

    public function destroy(
        Request $request,
        int $year,
        int $month,
        Transaction $transaction
    ): RedirectResponse {
        $transaction = $this->ownedTransaction($request, $transaction, $year, $month);
        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');

        $this->transactionMutationService->destroy($request->user(), $transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.deleted'));
    }

    public function restore(
        Request $request,
        int $year,
        int $month,
        string $transactionUuid
    ): RedirectResponse {
        $transaction = $this->ownedTransactionByUuid($request, $transactionUuid, $year, $month, true);
        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');

        $this->transactionMutationService->restore($request->user(), $transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.restored'));
    }

    public function forceDestroy(
        Request $request,
        int $year,
        int $month,
        string $transactionUuid
    ): RedirectResponse {
        $transaction = $this->ownedTransactionByUuid($request, $transactionUuid, $year, $month, true);
        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');

        $this->transactionMutationService->forceDelete($request->user(), $transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.force_deleted'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function showPayload(array $data, int $year, int $month): array
    {
        return [
            'monthlySheet' => $data,
            'transactionsPage' => [
                'year' => $year,
                'month' => $month,
                'month_label' => $data['period']['month_label'],
                'period_label' => mb_strtolower($data['period']['month_label']).' '.$year,
                'records_count' => $data['meta']['transactions_count'],
                'last_recorded_at' => $data['meta']['last_recorded_at'],
            ],
            'year' => $year,
            'month' => $month,
        ];
    }

    protected function ownedTransaction(
        Request $request,
        Transaction $transaction,
        int $year,
        int $month
    ): Transaction {
        abort_unless($transaction->user_id === $request->user()->id, 404);

        if (
            (int) $transaction->transaction_date?->year !== $year
            || (int) $transaction->transaction_date?->month !== $month
        ) {
            throw ValidationException::withMessages([
                'transaction' => 'La registrazione selezionata non appartiene al mese visualizzato.',
            ]);
        }

        return $transaction;
    }

    protected function ownedTransactionByUuid(
        Request $request,
        string $transactionUuid,
        int $year,
        int $month,
        bool $withTrashed = false
    ): Transaction {
        $query = $withTrashed
            ? Transaction::withTrashed()
            : Transaction::query();

        $transaction = $query->where('uuid', $transactionUuid)->firstOrFail();

        return $this->ownedTransaction($request, $transaction, $year, $month);
    }
}
