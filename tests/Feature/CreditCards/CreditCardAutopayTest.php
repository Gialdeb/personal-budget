<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Jobs\Automation\RunCreditCardAutopayJob;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\AutomationRun;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditCardCycleCharge;
use App\Models\NotificationTopic;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Notifications\CreditCardAutopayCompletedNotification;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Categories\CategoryFoundationService;
use App\Services\CreditCards\CreditCardAutopayService;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Transactions\TransactionMutationService;
use Carbon\CarbonImmutable;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('keeps credit card transactions on the card account without moving the linked payment account immediately', function () {
    $context = creditCardContext();

    $this->actingAs($context['user'])
        ->post(route('transactions.store', [
            'year' => 2026,
            'month' => 1,
        ]), [
            'transaction_day' => 20,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 120,
            'description' => 'Spesa carta gennaio',
        ])
        ->assertSessionHasNoErrors();

    $cardTransaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa carta gennaio')
        ->firstOrFail();

    expect((int) $cardTransaction->account_id)->toBe($context['creditCardAccount']->id)
        ->and(Transaction::query()
            ->where('account_id', $context['paymentAccount']->id)
            ->where('description', 'Spesa carta gennaio')
            ->exists())->toBeFalse();

    $context['paymentAccount']->refresh();
    $context['creditCardAccount']->refresh();

    expect((float) $context['paymentAccount']->current_balance)->toBe(3000.0)
        ->and((float) $context['creditCardAccount']->current_balance)->toBe(-120.0);
});

it('blocks a credit card expense that exceeds the configured credit limit', function () {
    $context = creditCardContext(creditLimit: 100);

    $this->actingAs($context['user'])
        ->post(route('transactions.store', [
            'year' => 2026,
            'month' => 1,
        ]), [
            'transaction_day' => 10,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 120,
            'description' => 'Spesa oltre plafond',
        ])
        ->assertSessionHasErrors('amount');
});

it('treats credit card refunds as exposure reductions before a later charge', function () {
    $context = creditCardContext(creditLimit: 100);

    storeCreditCardTransaction($this, $context, 2026, 1, 10, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 90, 'Spesa iniziale');
    storeCreditCardTransaction($this, $context, 2026, 1, 11, CategoryGroupTypeEnum::INCOME->value, $context['refundCategory'], 30, 'Rimborso carta');

    $this->actingAs($context['user'])
        ->post(route('transactions.store', [
            'year' => 2026,
            'month' => 1,
        ]), [
            'transaction_day' => 12,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 35,
            'description' => 'Spesa dopo rimborso',
        ])
        ->assertSessionHasNoErrors();

    $context['creditCardAccount']->refresh();

    expect((float) $context['creditCardAccount']->current_balance)->toBe(-95.0);
});

it('lets editable credit card transactions create a refund with an autonomous date and realigns an already charged cycle', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Carta gennaio da rimborsare');

    $transaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Carta gennaio da rimborsare')
        ->firstOrFail();

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $this->actingAs($context['user'])
        ->post(route('transactions.refund', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_date' => '2026-02-20',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertSessionHas('success', __('transactions.flash.refund_created'));

    $refund = $transaction->fresh()->refundTransaction;

    expect($refund)->not->toBeNull()
        ->and($refund->account_id)->toBe($context['creditCardAccount']->id)
        ->and($refund->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and((float) $refund->amount)->toBe(125.0)
        ->and($refund->transaction_date?->toDateString())->toBe('2026-02-20');

    $context['paymentAccount']->refresh();
    $context['creditCardAccount']->refresh();

    expect((float) $context['paymentAccount']->current_balance)->toBe(3000.0)
        ->and((float) $context['creditCardAccount']->current_balance)->toBe(0.0)
        ->and(Transaction::query()
            ->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)
            ->exists())->toBeFalse();
});

it('shows the expected charge reference for credit card expenses and out-of-cycle refunds in the monthly payload', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 14, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa 14 gennaio');
    storeCreditCardTransaction($this, $context, 2026, 1, 24, CategoryGroupTypeEnum::INCOME->value, $context['refundCategory'], 25, 'Rimborso 24 gennaio');

    $sheet = app(MonthlyTransactionSheetService::class)->build($context['user'], 2026, 1);
    $transactions = collect($sheet['transactions']);

    expect($transactions->contains(fn ($transaction) => ($transaction['date'] ?? null) === '2026-01-14'
        && ($transaction['direction'] ?? null) === TransactionDirectionEnum::EXPENSE->value
        && (float) ($transaction['amount_value_raw'] ?? 0) === 125.0
        && ($transaction['is_credit_card_transaction'] ?? false) === true
        && ($transaction['credit_card_payment_due_date'] ?? null) === '2026-01-16'))->toBeTrue()
        ->and($transactions->contains(fn ($transaction) => ($transaction['date'] ?? null) === '2026-01-24'
            && ($transaction['direction'] ?? null) === TransactionDirectionEnum::INCOME->value
            && (float) ($transaction['amount_value_raw'] ?? 0) === 25.0
            && ($transaction['is_credit_card_transaction'] ?? false) === true
            && ($transaction['credit_card_payment_due_date'] ?? null) === '2026-02-16'))->toBeTrue();
});

it('does not expose credit card settlement rows as refundable transactions', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Carta gennaio settlement guard');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $this->actingAs($context['user'])
        ->get(route('transactions.show', ['year' => 2026, 'month' => 2]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => ($transaction['kind'] ?? null) === TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value
                    && ($transaction['can_refund'] ?? false) === true))
        );
});

it('charges the credit card cycle automatically, stays idempotent, and does not double count the technical payment in dashboard reports', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 500, 'Carta gennaio 1');
    storeCreditCardTransaction($this, $context, 2026, 2, 10, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 200, 'Carta febbraio 1');
    storeCreditCardTransaction($this, $context, 2026, 2, 12, CategoryGroupTypeEnum::INCOME->value, $context['refundCategory'], 30, 'Rimborso febbraio');

    $service = app(CreditCardAutopayService::class);
    $firstRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));
    $secondRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail();
    $context['paymentAccount']->refresh();
    $context['creditCardAccount']->refresh();

    expect($firstRun)->toMatchArray([
        'examined_count' => 1,
        'processed_count' => 1,
        'success_count' => 1,
        'due_count' => 1,
        'charged_count' => 1,
    ])->and($secondRun)->toMatchArray([
        'examined_count' => 1,
        'processed_count' => 1,
        'success_count' => 1,
        'due_count' => 1,
        'charged_count' => 0,
        'skipped_count' => 1,
    ]);

    expect(data_get($firstRun, 'account_results.0.status'))->toBe('charged')
        ->and(data_get($secondRun, 'account_results.0.status'))->toBe('already_processed');

    expect(CreditCardCycleCharge::query()->count())->toBe(1)
        ->and((float) $cycleCharge->charged_amount)->toBe(670.0)
        ->and($cycleCharge->cycle_end_date?->toDateString())->toBe('2026-02-15')
        ->and($cycleCharge->payment_due_date?->toDateString())->toBe('2026-02-16')
        ->and(Transaction::query()->where('is_transfer', true)->count())->toBe(2)
        ->and(Transaction::query()->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)->count())->toBe(2)
        ->and(Category::query()->where('user_id', $context['user']->id)->where('group_type', CategoryGroupTypeEnum::TRANSFER->value)->where('is_selectable', true)->exists())->toBeFalse()
        ->and(Category::query()->where('user_id', $context['user']->id)->where('foundation_key', CategoryFoundationService::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY)->where('is_selectable', false)->exists())->toBeTrue()
        ->and((float) $context['paymentAccount']->current_balance)->toBe(2330.0)
        ->and((float) $context['creditCardAccount']->current_balance)->toBe(0.0);

    $dashboard = app(DashboardService::class)->build($context['user'], 2026, 2);

    expect((float) $dashboard['overview']['expense_total_raw'])->toBe(200.0)
        ->and((float) $dashboard['overview']['income_total_raw'])->toBe(30.0);
});

it('notifies the user by email and in app only when a new real credit card charge is created', function () {
    Notification::fake();

    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 200, 'Carta gennaio');

    $service = app(CreditCardAutopayService::class);
    $firstRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));
    $secondRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    Notification::assertSentTo(
        $context['user'],
        CreditCardAutopayCompletedNotification::class,
        function ($notification, array $channels) use ($context) {
            return $channels === ['mail', 'database']
                && $notification->toDatabase($context['user'])['topic'] === 'credit_card_autopay_completed';
        },
    );

    Notification::assertSentToTimes($context['user'], CreditCardAutopayCompletedNotification::class, 1);

    expect($firstRun['notified_count'])->toBe(1)
        ->and($secondRun['notified_count'])->toBe(0);
});

it('does not send the credit card charge communication when the user disables both channels', function () {
    Notification::fake();

    $context = creditCardContext();
    $topic = NotificationTopic::query()->where('key', 'credit_card_autopay_completed')->firstOrFail();

    $context['user']->notificationPreferences()->updateOrCreate(
        ['notification_topic_id' => $topic->id],
        [
            'email_enabled' => false,
            'in_app_enabled' => false,
            'sms_enabled' => false,
        ],
    );

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 200, 'Carta gennaio');

    $result = app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    Notification::assertNothingSent();

    expect($result['notified_count'])->toBe(0);
});

it('keeps already processed credit card cycles closed when payment day or closing day changes later', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 100, 'Gennaio ciclo 1');

    $service = app(CreditCardAutopayService::class);
    $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $context['creditCardAccount']->forceFill([
        'settings' => [
            ...$context['creditCardAccount']->creditCardSettings(),
            'statement_closing_day' => 20,
            'payment_day' => 25,
        ],
    ])->save();

    storeCreditCardTransaction($this, $context, 2026, 2, 18, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 150, 'Febbraio nuovo ciclo');
    storeCreditCardTransaction($this, $context, 2026, 3, 5, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 50, 'Marzo nuovo ciclo');

    $service->runAutomationPipeline(CarbonImmutable::parse('2026-03-25'));

    $charges = CreditCardCycleCharge::query()
        ->orderBy('cycle_end_date')
        ->get();

    expect($charges)->toHaveCount(2)
        ->and($charges->pluck('cycle_end_date')->map->toDateString()->all())->toBe([
            '2026-02-15',
            '2026-03-20',
        ])
        ->and($charges->pluck('charged_amount')->map(fn ($value) => round((float) $value, 2))->all())->toBe([
            100.0,
            200.0,
        ]);
});

it('realigns future payment due dates when payment day changes without reopening processed cycles or duplicating charges', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 100, 'Gennaio payment day change');

    $service = app(CreditCardAutopayService::class);
    $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $context['creditCardAccount']->forceFill([
        'settings' => [
            ...$context['creditCardAccount']->creditCardSettings(),
            'payment_day' => 20,
        ],
    ])->save();

    storeCreditCardTransaction($this, $context, 2026, 2, 18, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 150, 'Febbraio payment day change');

    $firstMarchRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-03-20'));
    $secondMarchRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-03-20'));

    $charges = CreditCardCycleCharge::query()->orderBy('cycle_end_date')->get();

    expect($charges)->toHaveCount(2)
        ->and($charges->pluck('cycle_end_date')->map->toDateString()->all())->toBe([
            '2026-02-15',
            '2026-03-15',
        ])
        ->and($charges->pluck('payment_due_date')->map->toDateString()->all())->toBe([
            '2026-02-16',
            '2026-03-20',
        ])
        ->and($charges->pluck('charged_amount')->map(fn ($value) => round((float) $value, 2))->all())->toBe([
            100.0,
            150.0,
        ])
        ->and(data_get($firstMarchRun, 'account_results.0.status'))->toBe('charged')
        ->and(data_get($secondMarchRun, 'account_results.0.status'))->toBe('already_processed')
        ->and((float) $context['paymentAccount']->fresh()->current_balance)->toBe(2750.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0);
});

it('realigns future cycle boundaries when statement closing day changes and stays coherent with refunds recorded after the new closing date', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 100, 'Gennaio closing change');

    $service = app(CreditCardAutopayService::class);
    $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $context['creditCardAccount']->forceFill([
        'settings' => [
            ...$context['creditCardAccount']->creditCardSettings(),
            'statement_closing_day' => 20,
            'payment_day' => 25,
        ],
    ])->save();

    storeCreditCardTransaction($this, $context, 2026, 2, 18, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 150, 'Febbraio closing change');

    $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-25'));

    $februaryExpense = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Febbraio closing change')
        ->firstOrFail();

    app(TransactionRefundService::class)->refund($februaryExpense, [
        'transaction_date' => '2026-03-05',
        'amount' => 50,
        'description' => 'Rimborso marzo dopo cambio chiusura',
    ]);

    $marchRun = $service->runAutomationPipeline(CarbonImmutable::parse('2026-03-25'));
    $charges = CreditCardCycleCharge::query()->orderBy('cycle_end_date')->get();
    $secondCharge = $charges->get(1);
    $thirdCharge = $charges->get(2);

    expect($charges)->toHaveCount(3)
        ->and($charges->pluck('cycle_end_date')->map->toDateString()->all())->toBe([
            '2026-02-15',
            '2026-02-20',
            '2026-03-20',
        ])
        ->and($charges->pluck('payment_due_date')->map->toDateString()->all())->toBe([
            '2026-02-16',
            '2026-02-25',
            '2026-03-25',
        ])
        ->and($charges->pluck('charged_amount')->map(fn ($value) => round((float) $value, 2))->all())->toBe([
            100.0,
            150.0,
            0.0,
        ])
        ->and($secondCharge)->not->toBeNull()
        ->and((float) $secondCharge->paymentTransaction()->firstOrFail()->amount)->toBe(100.0)
        ->and((float) data_get($secondCharge->meta, 'adjustment_amount_total'))->toBe(-50.0)
        ->and((float) data_get($secondCharge->meta, 'current_charged_amount'))->toBe(100.0)
        ->and($thirdCharge)->not->toBeNull()
        ->and((float) $thirdCharge->charged_amount)->toBe(0.0)
        ->and(data_get($marchRun, 'account_results.0.status'))->toBe('zero_amount')
        ->and((float) $context['paymentAccount']->fresh()->current_balance)->toBe(2800.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0);
});

it('runs the credit card autopay job through the automation runner', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 90, 'Job cycle expense');

    $this->travelTo(CarbonImmutable::parse('2026-02-16 08:00:00'));

    $job = app(RunCreditCardAutopayJob::class);
    $job->handle(
        app(AutomationPipelineRunner::class),
        app(CreditCardAutopayService::class),
    );

    $run = AutomationRun::query()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run->automation_key)->toBe('credit_card_autopay')
        ->and($run->processed_count)->toBe(1)
        ->and($run->success_count)->toBe(1)
        ->and(data_get($run->result, 'account_results.0.status'))->toBe('charged');
});

it('runs the credit card autopay from admin automation with a reference date and stays idempotent', function () {
    Notification::fake();

    $context = creditCardContext();
    $admin = adminUser();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 220, 'Spesa gennaio admin');

    $this->actingAs($admin)
        ->from(route('admin.automation.index'))
        ->post(route('admin.automation.run', ['pipeline' => 'credit_card_autopay']), [
            'reference_date' => '2026-02-16',
        ])
        ->assertRedirect(route('admin.automation.index'))
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->from(route('admin.automation.index'))
        ->post(route('admin.automation.run', ['pipeline' => 'credit_card_autopay']), [
            'reference_date' => '2026-02-16',
        ])
        ->assertRedirect(route('admin.automation.index'))
        ->assertSessionHas('success');

    $run = AutomationRun::query()
        ->where('automation_key', 'credit_card_autopay')
        ->latest('id')
        ->firstOrFail();

    expect(CreditCardCycleCharge::query()->count())->toBe(1)
        ->and(Transaction::query()->where('is_transfer', true)->count())->toBe(2)
        ->and(data_get($run->context, 'reference_date'))->toBe('2026-02-16')
        ->and(data_get($run->result, 'account_results.0.status'))->toBe('already_processed');
});

it('reports a zero-amount due cycle as a business no-op instead of an error', function () {
    $context = creditCardContext();

    $result = app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    expect($result)->toMatchArray([
        'examined_count' => 1,
        'processed_count' => 1,
        'due_count' => 1,
        'success_count' => 1,
        'error_count' => 0,
        'charged_count' => 0,
        'skipped_count' => 1,
    ])->and(data_get($result, 'account_results.0.status'))->toBe('zero_amount')
        ->and(data_get($result, 'account_results.0.cycle_start_date'))->toBe('2026-01-16')
        ->and(data_get($result, 'account_results.0.cycle_end_date'))->toBe('2026-02-15');

    expect(CreditCardCycleCharge::query()->count())->toBe(1)
        ->and((float) CreditCardCycleCharge::query()->firstOrFail()->charged_amount)->toBe(0.0)
        ->and(Transaction::query()->where('is_transfer', true)->count())->toBe(0);
});

it('explains the real january expense scenario with a february due date as a created charge', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa reale gennaio');

    $result = app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    expect($result)->toMatchArray([
        'examined_count' => 1,
        'processed_count' => 1,
        'due_count' => 1,
        'success_count' => 1,
        'error_count' => 0,
        'charged_count' => 1,
        'skipped_count' => 0,
    ])->and(data_get($result, 'account_results.0.status'))->toBe('charged')
        ->and(data_get($result, 'account_results.0.charged_amount'))->toBe(125.0);
});

it('protects the generated credit card settlement from manual update and delete', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa da proteggere');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $settlement = Transaction::query()
        ->where('account_id', $context['paymentAccount']->id)
        ->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)
        ->firstOrFail();

    $this->actingAs($context['user'])
        ->from(route('transactions.show', ['year' => 2026, 'month' => 2]))
        ->patch(route('transactions.update', [
            'year' => 2026,
            'month' => 2,
            'transaction' => $settlement->uuid,
        ]), [
            'transaction_day' => 16,
            'type_key' => CategoryGroupTypeEnum::TRANSFER->value,
            'account_id' => $context['paymentAccount']->id,
            'destination_account_id' => $context['creditCardAccount']->id,
            'amount' => 125,
            'description' => 'Tentativo modifica regolamento',
        ])
        ->assertSessionHasErrors('transaction');

    $this->actingAs($context['user'])
        ->from(route('transactions.show', ['year' => 2026, 'month' => 2]))
        ->delete(route('transactions.destroy', [
            'year' => 2026,
            'month' => 2,
            'transaction' => $settlement->uuid,
        ]))
        ->assertSessionHasErrors('transaction');
});

it('shows the credit card settlement on the linked payment account but keeps the card-side counterpart out of the normal transactions view', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa gennaio');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $this->actingAs($context['user'])
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn ($page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => ($transaction['kind'] ?? null) === TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value
                    && ($transaction['account_uuid'] ?? null) === $context['paymentAccount']->uuid))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => ($transaction['kind'] ?? null) === TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value
                    && ($transaction['account_uuid'] ?? null) === $context['creditCardAccount']->uuid) === false)
            ->where('monthlySheet.deleted_transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => ($transaction['kind'] ?? null) === TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value
                    && ($transaction['account_uuid'] ?? null) === $context['creditCardAccount']->uuid) === false));
});

it('reconciles a processed cycle correctly when a historical card expense amount decreases later', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa storica gennaio');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $transaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa storica gennaio')
        ->firstOrFail();

    $this->actingAs($context['user'])
        ->from(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->patch(route('transactions.update', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 13,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 100,
            'description' => 'Spesa storica gennaio',
        ])
        ->assertSessionHasNoErrors();

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail()->refresh();

    expect((float) $context['paymentAccount']->fresh()->current_balance)->toBe(2900.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0)
        ->and((float) $cycleCharge->paymentTransaction()->firstOrFail()->amount)->toBe(100.0)
        ->and((float) data_get($cycleCharge->meta, 'adjustment_amount_total'))->toBe(-25.0)
        ->and((float) data_get($cycleCharge->meta, 'current_charged_amount'))->toBe(100.0)
        ->and(collect(data_get($cycleCharge->meta, 'adjustments', [])))->toHaveCount(1)
        ->and(Transaction::query()->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)->count())->toBe(2);
});

it('reconciles a processed cycle with a technical delta when a historical card expense amount increases later', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa storica gennaio');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $transaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa storica gennaio')
        ->firstOrFail();

    $this->actingAs($context['user'])
        ->from(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->patch(route('transactions.update', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 13,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 150,
            'description' => 'Spesa storica gennaio',
        ])
        ->assertSessionHasNoErrors();

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail()->refresh();

    expect((float) $context['paymentAccount']->fresh()->current_balance)->toBe(2850.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0)
        ->and((float) $cycleCharge->paymentTransaction()->firstOrFail()->amount)->toBe(150.0)
        ->and((float) data_get($cycleCharge->meta, 'adjustment_amount_total'))->toBe(25.0)
        ->and((float) data_get($cycleCharge->meta, 'current_charged_amount'))->toBe(150.0)
        ->and(collect(data_get($cycleCharge->meta, 'adjustments', [])))->toHaveCount(1)
        ->and(Transaction::query()->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)->count())->toBe(2);
});

it('reconciles a processed cycle with a technical reverse delta when a historical card expense is deleted later', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa da eliminare');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $transaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa da eliminare')
        ->firstOrFail();

    $this->actingAs($context['user'])
        ->from(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->delete(route('transactions.destroy', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]))
        ->assertSessionHasNoErrors();

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail()->refresh();

    expect((float) $context['paymentAccount']->fresh()->current_balance)->toBe(3000.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0)
        ->and($cycleCharge->paymentTransaction()->exists())->toBeFalse()
        ->and($cycleCharge->cardSettlementTransaction()->exists())->toBeFalse()
        ->and((float) data_get($cycleCharge->meta, 'adjustment_amount_total'))->toBe(-125.0)
        ->and((float) data_get($cycleCharge->meta, 'current_charged_amount'))->toBe(0.0)
        ->and(collect(data_get($cycleCharge->meta, 'adjustments', [])))->toHaveCount(1)
        ->and(Transaction::query()->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)->count())->toBe(0);
});

it('reconciles a processed cycle correctly when a total refund is recorded later', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa rimborsata total');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $originalTransaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa rimborsata total')
        ->firstOrFail();

    app(TransactionRefundService::class)->refund($originalTransaction, [
        'transaction_date' => '2026-03-01',
        'description' => 'Rimborso totale carta',
    ]);

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail()->refresh();

    expect((float) $context['paymentAccount']->fresh()->current_balance)->toBe(3000.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0)
        ->and($cycleCharge->paymentTransaction()->exists())->toBeFalse()
        ->and($cycleCharge->cardSettlementTransaction()->exists())->toBeFalse()
        ->and((float) data_get($cycleCharge->meta, 'adjustment_amount_total'))->toBe(-125.0)
        ->and((float) data_get($cycleCharge->meta, 'current_charged_amount'))->toBe(0.0)
        ->and(collect(data_get($cycleCharge->meta, 'adjustments', [])))->toHaveCount(1);
});

it('reconciles a processed cycle correctly when a partial refund is recorded later', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa rimborsata partial');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $originalTransaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa rimborsata partial')
        ->firstOrFail();

    app(TransactionRefundService::class)->refund($originalTransaction, [
        'transaction_date' => '2026-03-01',
        'amount' => 25,
        'description' => 'Rimborso parziale carta',
    ]);

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail()->refresh();

    expect((float) $context['paymentAccount']->fresh()->current_balance)->toBe(2900.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0)
        ->and((float) $cycleCharge->paymentTransaction()->firstOrFail()->amount)->toBe(100.0)
        ->and((float) data_get($cycleCharge->meta, 'adjustment_amount_total'))->toBe(-25.0)
        ->and((float) data_get($cycleCharge->meta, 'current_charged_amount'))->toBe(100.0)
        ->and(collect(data_get($cycleCharge->meta, 'adjustments', [])))->toHaveCount(1);
});

it('keeps a processed cycle perfectly squared across multiple successive mutations without duplicate no-op adjustments', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 13, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 125, 'Spesa multi-step');

    app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    $transaction = Transaction::query()
        ->where('account_id', $context['creditCardAccount']->id)
        ->where('description', 'Spesa multi-step')
        ->firstOrFail();

    $this->actingAs($context['user'])
        ->patch(route('transactions.update', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 13,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 150,
            'description' => 'Spesa multi-step',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($context['user'])
        ->patch(route('transactions.update', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 13,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 100,
            'description' => 'Spesa multi-step',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($context['user'])
        ->patch(route('transactions.update', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 13,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $context['expenseCategory']->uuid,
            'amount' => 100,
            'description' => 'Spesa multi-step',
        ])
        ->assertSessionHasNoErrors();

    $cycleCharge = CreditCardCycleCharge::query()->firstOrFail()->refresh();

    expect((float) $context['paymentAccount']->fresh()->current_balance)->toBe(2900.0)
        ->and((float) $context['creditCardAccount']->fresh()->current_balance)->toBe(0.0)
        ->and((float) $cycleCharge->paymentTransaction()->firstOrFail()->amount)->toBe(100.0)
        ->and((float) data_get($cycleCharge->meta, 'adjustment_amount_total'))->toBe(-25.0)
        ->and((float) data_get($cycleCharge->meta, 'current_charged_amount'))->toBe(100.0)
        ->and(collect(data_get($cycleCharge->meta, 'adjustments', [])))->toHaveCount(2)
        ->and(Transaction::query()->where('kind', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value)->count())->toBe(2);
});

it('reports a non-due card as a business no-op instead of an error', function () {
    $context = creditCardContext();

    $result = app(CreditCardAutopayService::class)->runAutomationPipeline(CarbonImmutable::parse('2026-02-15'));

    expect($result)->toMatchArray([
        'examined_count' => 1,
        'processed_count' => 0,
        'due_count' => 0,
        'success_count' => 1,
        'error_count' => 0,
        'charged_count' => 0,
        'skipped_count' => 1,
    ])->and(data_get($result, 'account_results.0.status'))->toBe('not_due');
});

it('reports an already processed cycle as a business no-op instead of an error', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 220, 'Spesa gennaio già addebitata');

    $service = app(CreditCardAutopayService::class);
    $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));
    $result = $service->runAutomationPipeline(CarbonImmutable::parse('2026-02-16'));

    expect($result)->toMatchArray([
        'examined_count' => 1,
        'processed_count' => 1,
        'due_count' => 1,
        'success_count' => 1,
        'error_count' => 0,
        'charged_count' => 0,
        'skipped_count' => 1,
    ])->and(data_get($result, 'account_results.0.status'))->toBe('already_processed');
});

it('reports a real autopay execution error coherently in the automation run payload', function () {
    $context = creditCardContext();

    storeCreditCardTransaction($this, $context, 2026, 1, 20, CategoryGroupTypeEnum::EXPENSE->value, $context['expenseCategory'], 180, 'Spesa con errore tecnico');

    $serviceMock = Mockery::mock(TransactionMutationService::class);
    $serviceMock->shouldReceive('storeGeneratedCreditCardSettlementBetweenAccounts')
        ->once()
        ->andThrow(new RuntimeException('Impossibile creare il regolamento tecnico.'));

    app()->instance(TransactionMutationService::class, $serviceMock);

    (new RunCreditCardAutopayJob(
        AutomationTriggerTypeEnum::MANUAL,
        '2026-02-16',
    ))->handle(
        app(AutomationPipelineRunner::class),
        app(CreditCardAutopayService::class),
    );

    $run = AutomationRun::query()->latest('id')->firstOrFail();

    expect($run->status->value)->toBe('warning')
        ->and($run->error_count)->toBe(1)
        ->and($run->success_count)->toBe(0)
        ->and(data_get($run->result, 'account_results.0.status'))->toBe('execution_error')
        ->and(data_get($run->result, 'account_results.0.detail'))->toContain('Impossibile creare il regolamento tecnico');
});

function creditCardContext(
    float $creditLimit = 1000,
    int $statementClosingDay = 15,
    int $paymentDay = 16,
): array {
    $user = User::factory()->create();

    ensureCreditCardYearContext($user, 2026);

    $paymentType = AccountType::query()->firstOrCreate([
        'code' => 'payment_account',
    ], [
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $creditCardType = AccountType::query()->firstOrCreate([
        'code' => 'credit_card',
    ], [
        'name' => 'Carta di credito',
        'balance_nature' => AccountBalanceNatureEnum::LIABILITY->value,
    ]);

    $paymentAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $paymentType->id,
        'name' => 'Conto collegato',
        'currency' => 'EUR',
        'opening_balance' => 3000,
        'current_balance' => 3000,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $creditCardAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $creditCardType->id,
        'name' => 'Carta principale',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
        'settings' => [
            'credit_limit' => $creditLimit,
            'linked_payment_account_id' => $paymentAccount->id,
            'statement_closing_day' => $statementClosingDay,
            'payment_day' => $paymentDay,
            'auto_pay' => true,
        ],
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $refundCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Rimborsi carta',
        'slug' => 'rimborsi-carta-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'tracked_item_id' => null,
        'scope_id' => null,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
    ]);

    return compact(
        'user',
        'paymentAccount',
        'creditCardAccount',
        'expenseCategory',
        'refundCategory',
    );
}

function ensureCreditCardYearContext(User $user, int $year): void
{
    UserYear::query()->updateOrCreate([
        'user_id' => $user->id,
        'year' => $year,
    ], [
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => $year,
        'base_currency' => 'EUR',
        'base_currency_code' => 'EUR',
    ]);
}

function storeCreditCardTransaction(
    $testCase,
    array $context,
    int $year,
    int $month,
    int $day,
    string $typeKey,
    Category $category,
    float $amount,
    string $description,
): void {
    $testCase->actingAs($context['user'])
        ->post(route('transactions.store', [
            'year' => $year,
            'month' => $month,
        ]), [
            'transaction_day' => $day,
            'type_key' => $typeKey,
            'account_uuid' => $context['creditCardAccount']->uuid,
            'category_uuid' => $category->uuid,
            'amount' => $amount,
            'description' => $description,
        ])
        ->assertSessionHasNoErrors();
}

function adminUser(): User
{
    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    return $admin;
}
