<?php

namespace App\Services\CreditCards;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use App\Models\CreditCardCycleCharge;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use App\Services\Transactions\BalanceAdjustmentService;
use App\Services\Transactions\TransactionMutationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditCardAutopayService
{
    public function __construct(
        protected BalanceAdjustmentService $balanceAdjustmentService,
        protected TransactionMutationService $transactionMutationService,
        protected CommunicationService $communicationService,
    ) {}

    /**
     * @return array{
     *     examined_count: int,
     *     processed_count: int,
     *     due_count: int,
     *     success_count: int,
     *     warning_count: int,
     *     error_count: int,
     *     charged_count: int,
     *     skipped_count: int,
     *     notified_count: int,
     *     account_results: array<int, array{
     *         account_id: int,
     *         account_uuid: string|null,
     *         account_name: string,
     *         status: string,
     *         technical_error: bool,
     *         reference_date: string,
     *         cycle_start_date: string|null,
     *         cycle_end_date: string|null,
     *         payment_due_date: string|null,
     *         charged_amount: float|null,
     *         cycle_charge_id: int|null,
     *         detail: string|null,
     *         exception_class: string|null
     *     }>
     * }
     */
    public function runAutomationPipeline(?CarbonImmutable $today = null): array
    {
        $referenceDate = ($today ?? CarbonImmutable::today(config('app.timezone')))->startOfDay();
        $examinedCount = 0;
        $processedCount = 0;
        $dueCount = 0;
        $successCount = 0;
        $warningCount = 0;
        $errorCount = 0;
        $chargedCount = 0;
        $skippedCount = 0;
        $notifiedCount = 0;
        $accountResults = [];

        /** @var EloquentCollection<int, Account> $accounts */
        $accounts = Account::query()
            ->with('accountType:id,code')
            ->where('is_active', true)
            ->whereHas('accountType', fn ($query) => $query->where('code', AccountTypeCodeEnum::CREDIT_CARD->value))
            ->orderBy('id')
            ->get(['accounts.*']);

        foreach ($accounts as $account) {
            $examinedCount++;

            $inspection = $this->inspectAccountForReferenceDate($account, $referenceDate);

            if (($inspection['is_due'] ?? false) === true) {
                $dueCount++;
            }

            if (($inspection['status'] ?? null) !== 'due') {
                $successCount++;
                $skippedCount++;
                $accountResults[] = $inspection;

                continue;
            }

            $processedCount++;

            try {
                $result = $this->processDueCycle($account, $inspection['cycle'], $referenceDate);

                if (($result['status'] ?? null) === 'charged') {
                    $chargedCount++;
                } else {
                    $skippedCount++;
                }

                if (($result['notified'] ?? false) === true) {
                    $notifiedCount++;
                }

                $successCount++;
                $accountResults[] = [
                    ...$inspection,
                    ...$result,
                    'technical_error' => false,
                    'exception_class' => null,
                ];
            } catch (ValidationException $exception) {
                report($exception);
                $errorCount++;
                $accountResults[] = [
                    ...$inspection,
                    'status' => 'configuration_error',
                    'technical_error' => true,
                    'charged_amount' => null,
                    'cycle_charge_id' => null,
                    'detail' => $exception->getMessage(),
                    'exception_class' => $exception::class,
                ];
            } catch (\Throwable $exception) {
                report($exception);
                $errorCount++;
                $accountResults[] = [
                    ...$inspection,
                    'status' => 'execution_error',
                    'technical_error' => true,
                    'charged_amount' => null,
                    'cycle_charge_id' => null,
                    'detail' => $exception->getMessage(),
                    'exception_class' => $exception::class,
                ];
            }
        }

        return [
            'examined_count' => $examinedCount,
            'processed_count' => $processedCount,
            'due_count' => $dueCount,
            'success_count' => $successCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'charged_count' => $chargedCount,
            'skipped_count' => $skippedCount,
            'notified_count' => $notifiedCount,
            'account_results' => $accountResults,
        ];
    }

    /**
     * @param  array{
     *     cycle_start_date: CarbonImmutable,
     *     cycle_end_date: CarbonImmutable,
     *     payment_due_date: CarbonImmutable,
     *     statement_closing_day: int,
     *     payment_day: int
     * }  $cycle
     * @return array{
     *     cycle_charge_id:int,
     *     charged_amount:float,
     *     created_new_cycle:bool,
     *     notified:bool,
     *     status:'charged'|'already_processed'|'zero_amount',
     *     detail:string|null
     * }
     */
    public function processDueCycle(
        Account $creditCardAccount,
        array $cycle,
        ?CarbonImmutable $processingDate = null,
    ): array {
        $referenceDate = ($processingDate ?? CarbonImmutable::today(config('app.timezone')))->startOfDay();

        $result = DB::transaction(function () use ($creditCardAccount, $cycle, $referenceDate): array {
            $existingCharge = CreditCardCycleCharge::query()
                ->where('credit_card_account_id', $creditCardAccount->id)
                ->whereDate('cycle_end_date', $cycle['cycle_end_date']->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existingCharge instanceof CreditCardCycleCharge) {
                return [
                    'cycle_charge_id' => (int) $existingCharge->id,
                    'charged_amount' => round((float) $existingCharge->charged_amount, 2),
                    'created_new_cycle' => false,
                    'status' => 'already_processed',
                    'detail' => __('transactions.credit_card.autopay.reporting.already_processed'),
                ];
            }

            $linkedPaymentAccount = $this->resolveLinkedPaymentAccount($creditCardAccount);
            $balanceAtCycleEnd = round(
                $this->balanceAdjustmentService->theoreticalBalanceAt(
                    $creditCardAccount,
                    $cycle['cycle_end_date']->toDateString(),
                ),
                2,
            );
            $chargedAmount = round(max($balanceAtCycleEnd * -1, 0), 2);

            $paymentTransactionId = null;
            $cardSettlementTransactionId = null;

            if ($chargedAmount > 0) {
                $transferDescription = __('transactions.credit_card.autopay.description', [
                    'account' => $creditCardAccount->name,
                    'date' => $cycle['cycle_end_date']->format('d/m/Y'),
                ]);

                $transferPair = $this->transactionMutationService->storeGeneratedCreditCardSettlementBetweenAccounts(
                    $linkedPaymentAccount,
                    $creditCardAccount,
                    $chargedAmount,
                    $referenceDate->toDateString(),
                    (int) $creditCardAccount->user_id,
                    $transferDescription,
                    __('transactions.credit_card.autopay.notes'),
                );

                $paymentTransactionId = (int) $transferPair['source']->id;
                $cardSettlementTransactionId = (int) $transferPair['destination']->id;
            }

            $cycleCharge = CreditCardCycleCharge::query()->create([
                'credit_card_account_id' => $creditCardAccount->id,
                'linked_payment_account_id' => $linkedPaymentAccount->id,
                'payment_transaction_id' => $paymentTransactionId,
                'card_settlement_transaction_id' => $cardSettlementTransactionId,
                'cycle_start_date' => $cycle['cycle_start_date']->toDateString(),
                'cycle_end_date' => $cycle['cycle_end_date']->toDateString(),
                'payment_due_date' => $cycle['payment_due_date']->toDateString(),
                'statement_closing_day' => $cycle['statement_closing_day'],
                'payment_day' => $cycle['payment_day'],
                'balance_at_cycle_end' => $balanceAtCycleEnd,
                'charged_amount' => $chargedAmount,
                'processed_at' => $referenceDate,
                'meta' => [
                    'linked_payment_account_uuid' => $linkedPaymentAccount->uuid,
                    'credit_card_account_uuid' => $creditCardAccount->uuid,
                ],
            ]);

            return [
                'cycle_charge_id' => (int) $cycleCharge->id,
                'charged_amount' => $chargedAmount,
                'created_new_cycle' => true,
                'status' => $chargedAmount > 0 ? 'charged' : 'zero_amount',
                'detail' => $chargedAmount > 0
                    ? __('transactions.credit_card.autopay.reporting.charged')
                    : __('transactions.credit_card.autopay.reporting.zero_amount'),
            ];
        });

        $notified = false;

        if ($result['created_new_cycle'] && $result['charged_amount'] > 0) {
            $notified = $this->sendAutopayCompletedCommunication((int) $result['cycle_charge_id']);
        }

        return [
            ...$result,
            'notified' => $notified,
        ];
    }

    /**
     * @return array{
     *     cycle_start_date: CarbonImmutable,
     *     cycle_end_date: CarbonImmutable,
     *     payment_due_date: CarbonImmutable,
     *     statement_closing_day: int,
     *     payment_day: int
     * }|null
     */
    public function resolveDueCycleForDate(Account $creditCardAccount, CarbonImmutable $date): ?array
    {
        $inspection = $this->inspectAccountForReferenceDate($creditCardAccount, $date);

        return ($inspection['status'] ?? null) === 'due'
            ? $inspection['cycle']
            : null;
    }

    /**
     * @return array{
     *     cycle_start_date: CarbonImmutable,
     *     cycle_end_date: CarbonImmutable,
     *     payment_due_date: CarbonImmutable,
     *     statement_closing_day: int,
     *     payment_day: int
     * }|null
     */
    public function resolveCycleForTransactionDate(Account $creditCardAccount, CarbonImmutable $date): ?array
    {
        if (! $creditCardAccount->isCreditCard()) {
            return null;
        }

        $statementClosingDay = $creditCardAccount->creditCardStatementClosingDay();
        $paymentDay = $creditCardAccount->creditCardPaymentDay();

        if ($statementClosingDay === null || $paymentDay === null) {
            return null;
        }

        $closingDateInTransactionMonth = $this->buildValidDate(
            (int) $date->year,
            (int) $date->month,
            $statementClosingDay,
        );

        $cycleEndDate = $date->lessThanOrEqualTo($closingDateInTransactionMonth)
            ? $closingDateInTransactionMonth
            : $this->buildValidDate(
                (int) $date->addMonthNoOverflow()->year,
                (int) $date->addMonthNoOverflow()->month,
                $statementClosingDay,
            );

        $paymentBaseDate = $paymentDay > $statementClosingDay
            ? $cycleEndDate
            : $cycleEndDate->addMonthNoOverflow();

        $previousClosingDate = $this->buildValidDate(
            (int) $cycleEndDate->subMonthNoOverflow()->year,
            (int) $cycleEndDate->subMonthNoOverflow()->month,
            $statementClosingDay,
        );

        return [
            'cycle_start_date' => $previousClosingDate->addDay(),
            'cycle_end_date' => $cycleEndDate,
            'payment_due_date' => $this->buildValidDate(
                (int) $paymentBaseDate->year,
                (int) $paymentBaseDate->month,
                $paymentDay,
            ),
            'statement_closing_day' => $statementClosingDay,
            'payment_day' => $paymentDay,
        ];
    }

    /**
     * @return array{
     *     account_id: int,
     *     account_uuid: string|null,
     *     account_name: string,
     *     status: 'due'|'autopay_disabled'|'configuration_error'|'not_due',
     *     technical_error: bool,
     *     reference_date: string,
     *     cycle_start_date: string|null,
     *     cycle_end_date: string|null,
     *     payment_due_date: string|null,
     *     charged_amount: float|null,
     *     cycle_charge_id: int|null,
     *     detail: string|null,
     *     exception_class: string|null,
     *     is_due: bool,
     *     cycle: array{
     *         cycle_start_date: CarbonImmutable,
     *         cycle_end_date: CarbonImmutable,
     *         payment_due_date: CarbonImmutable,
     *         statement_closing_day: int,
     *         payment_day: int
     *     }|null
     * }
     */
    protected function inspectAccountForReferenceDate(Account $creditCardAccount, CarbonImmutable $date): array
    {
        $base = [
            'account_id' => (int) $creditCardAccount->id,
            'account_uuid' => $creditCardAccount->uuid,
            'account_name' => $creditCardAccount->name,
            'technical_error' => false,
            'reference_date' => $date->toDateString(),
            'cycle_start_date' => null,
            'cycle_end_date' => null,
            'payment_due_date' => null,
            'charged_amount' => null,
            'cycle_charge_id' => null,
            'detail' => null,
            'exception_class' => null,
            'is_due' => false,
            'cycle' => null,
        ];

        if (! $creditCardAccount->isCreditCard()) {
            return [
                ...$base,
                'status' => 'configuration_error',
                'technical_error' => true,
                'detail' => __('transactions.credit_card.autopay.reporting.not_a_credit_card'),
            ];
        }

        if (! $creditCardAccount->creditCardAutoPay()) {
            return [
                ...$base,
                'status' => 'autopay_disabled',
                'detail' => __('transactions.credit_card.autopay.reporting.autopay_disabled'),
            ];
        }

        $statementClosingDay = $creditCardAccount->creditCardStatementClosingDay();
        $paymentDay = $creditCardAccount->creditCardPaymentDay();
        $linkedPaymentAccountId = $creditCardAccount->creditCardLinkedPaymentAccountId();

        if (
            $statementClosingDay === null
            || $paymentDay === null
            || $linkedPaymentAccountId === null
        ) {
            return [
                ...$base,
                'status' => 'configuration_error',
                'technical_error' => true,
                'detail' => __('transactions.credit_card.autopay.reporting.configuration_missing'),
            ];
        }

        $paymentDueDate = $this->buildValidDate(
            (int) $date->year,
            (int) $date->month,
            $paymentDay,
        );

        $cycleEndDate = $paymentDay > $statementClosingDay
            ? $this->buildValidDate((int) $date->year, (int) $date->month, $statementClosingDay)
            : $this->buildValidDate(
                (int) $date->subMonthNoOverflow()->year,
                (int) $date->subMonthNoOverflow()->month,
                $statementClosingDay,
            );

        $previousClosingDate = $this->buildValidDate(
            (int) $cycleEndDate->subMonthNoOverflow()->year,
            (int) $cycleEndDate->subMonthNoOverflow()->month,
            $statementClosingDay,
        );

        $cycle = [
            'cycle_start_date' => $previousClosingDate->addDay(),
            'cycle_end_date' => $cycleEndDate,
            'payment_due_date' => $paymentDueDate,
            'statement_closing_day' => $statementClosingDay,
            'payment_day' => $paymentDay,
        ];

        if (! $paymentDueDate->isSameDay($date)) {
            return [
                ...$base,
                'status' => 'not_due',
                'cycle_start_date' => $cycle['cycle_start_date']->toDateString(),
                'cycle_end_date' => $cycle['cycle_end_date']->toDateString(),
                'payment_due_date' => $cycle['payment_due_date']->toDateString(),
                'detail' => __('transactions.credit_card.autopay.reporting.not_due'),
                'cycle' => $cycle,
            ];
        }

        return [
            ...$base,
            'status' => 'due',
            'cycle_start_date' => $cycle['cycle_start_date']->toDateString(),
            'cycle_end_date' => $cycle['cycle_end_date']->toDateString(),
            'payment_due_date' => $cycle['payment_due_date']->toDateString(),
            'is_due' => true,
            'cycle' => $cycle,
        ];
    }

    protected function resolveLinkedPaymentAccount(Account $creditCardAccount): Account
    {
        $linkedPaymentAccountId = $creditCardAccount->creditCardLinkedPaymentAccountId();

        $linkedPaymentAccount = $linkedPaymentAccountId === null
            ? null
            : Account::query()
                ->with('accountType:id,code')
                ->find($linkedPaymentAccountId);

        if (! $linkedPaymentAccount instanceof Account || ! $linkedPaymentAccount->is_active) {
            throw ValidationException::withMessages([
                'account' => __('transactions.credit_card.autopay.errors.linked_account_unavailable'),
            ]);
        }

        return $linkedPaymentAccount;
    }

    protected function buildValidDate(int $year, int $month, int $day): CarbonImmutable
    {
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return $monthStart->day(min($day, $monthStart->daysInMonth))->startOfDay();
    }

    protected function sendAutopayCompletedCommunication(int $cycleChargeId): bool
    {
        try {
            $cycleCharge = CreditCardCycleCharge::query()
                ->with([
                    'creditCardAccount.user:id,uuid,name,surname,email,locale,format_locale',
                    'linkedPaymentAccount:id,uuid,name',
                ])
                ->find($cycleChargeId);

            if (! $cycleCharge instanceof CreditCardCycleCharge) {
                return false;
            }

            $recipient = $cycleCharge->creditCardAccount?->user;

            if (! $recipient instanceof User) {
                return false;
            }

            $plan = $this->communicationService->send(
                'credit_card_autopay_completed',
                [
                    'credit_card_account_name' => $cycleCharge->creditCardAccount?->name,
                    'linked_payment_account_name' => $cycleCharge->linkedPaymentAccount?->name,
                    'charged_amount' => (float) $cycleCharge->charged_amount,
                    'currency' => $cycleCharge->creditCardAccount?->currency,
                    'payment_due_date' => $cycleCharge->payment_due_date?->toDateString(),
                    'cycle_end_date' => $cycleCharge->cycle_end_date?->toDateString(),
                ],
                $recipient,
            );

            return $plan->isNotEmpty();
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
