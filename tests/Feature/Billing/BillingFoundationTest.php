<?php

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Enums\BillingSubscriptionStatusEnum;
use App\Enums\BillingTransactionStatusEnum;
use App\Models\BillingPlan;
use App\Models\BillingTransaction;
use App\Models\User;
use App\Services\Billing\BillingSupportService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow(Date::parse('2026-04-01 10:00:00'));

    $this->seed(BillingPlanSeeder::class);
    $this->billingSupportService = app(BillingSupportService::class);
});

afterEach(function () {
    Date::setTestNow();
});

it('seeds the base billing plans', function () {
    $plans = BillingPlan::query()
        ->orderBy('id')
        ->get()
        ->keyBy('code');

    expect($plans->keys()->all())
        ->toBe(['free', 'supporter'])
        ->and($plans['free']->grants_supporter_access)->toBeFalse()
        ->and($plans['supporter']->grants_supporter_access)->toBeTrue()
        ->and($plans['supporter']->interval_unit)->toBe('year')
        ->and($plans['supporter']->duration_count)->toBe(1)
        ->and($plans['supporter']->reminder_days_before_end)->toBe(30);
});

it('self heals missing billing plans before resolving free and supporter plans', function () {
    BillingPlan::query()->delete();

    $freePlan = BillingPlan::free();
    $supporterPlan = BillingPlan::supporter();

    expect($freePlan->code)->toBe(BillingPlan::CODE_FREE)
        ->and($supporterPlan->code)->toBe(BillingPlan::CODE_SUPPORTER)
        ->and(BillingPlan::query()->count())->toBe(2);
});

it('creates a provider aware donation transaction before reconciliation', function () {
    $transaction = $this->billingSupportService->createDonationTransaction(
        BillingPlan::supporter(),
        [
            'provider' => BillingProviderEnum::Kofi,
            'provider_transaction_id' => 'kofi-donation-1',
            'provider_event_id' => 'evt-kofi-1',
            'customer_email' => 'supporter@example.com',
            'customer_name' => 'Supporter User',
            'currency' => 'eur',
            'amount' => '12.00',
            'status' => BillingTransactionStatusEnum::Paid,
            'paid_at' => Date::now(),
            'received_at' => Date::now(),
            'is_recurring' => true,
            'raw_payload' => ['verification_token' => 'future-webhook'],
            'metadata' => ['source' => 'landing-page'],
        ],
    );

    expect($transaction->provider)->toBe(BillingProviderEnum::Kofi)
        ->and($transaction->provider_transaction_id)->toBe('kofi-donation-1')
        ->and($transaction->provider_event_id)->toBe('evt-kofi-1')
        ->and($transaction->customer_email)->toBe('supporter@example.com')
        ->and($transaction->currency)->toBe('EUR')
        ->and($transaction->amount)->toBe('12.00')
        ->and($transaction->status)->toBe(BillingTransactionStatusEnum::Paid)
        ->and($transaction->is_recurring)->toBeTrue()
        ->and($transaction->user_id)->toBeNull()
        ->and($transaction->reconciliation_status)->toBe(BillingReconciliationStatusEnum::Pending)
        ->and($transaction->billing_plan_id)->toBe(BillingPlan::supporter()->id);
});

it('keeps the free plan always available without a blocking expiration', function () {
    $user = User::factory()->create();

    $subscription = $this->billingSupportService->ensureFreeSubscription($user);

    expect($subscription->status)->toBe(BillingSubscriptionStatusEnum::Free)
        ->and($subscription->billingPlan->code)->toBe('free')
        ->and($subscription->is_supporter)->toBeFalse()
        ->and($subscription->ends_at)->toBeNull()
        ->and($subscription->next_reminder_at)->toBeNull();

    $freshUser = $user->fresh();

    expect($freshUser->plan_code)->toBe('free')
        ->and($freshUser->subscription_status)->toBe('active')
        ->and($freshUser->subscription_ends_at)->toBeNull();
});

it('creates or upgrades a non blocking supporter window from a donation', function () {
    $user = User::factory()->create();

    $freeSubscription = $this->billingSupportService->ensureFreeSubscription($user);

    $transaction = $this->billingSupportService->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-donation-2',
        'provider_event_id' => 'evt-kofi-2',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '9.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
        'raw_payload' => ['provider' => 'kofi'],
        'metadata' => ['campaign' => 'spring'],
    ]);

    $subscription = $freeSubscription->fresh();

    expect($subscription->id)->toBe($freeSubscription->id)
        ->and($subscription->status)->toBe(BillingSubscriptionStatusEnum::Supporting)
        ->and($subscription->is_supporter)->toBeTrue()
        ->and($subscription->provider)->toBe(BillingProviderEnum::Kofi)
        ->and($subscription->started_at?->toDateTimeString())->toBe('2026-04-01 10:00:00')
        ->and($subscription->ends_at?->toDateTimeString())->toBe('2027-04-01 10:00:00')
        ->and($subscription->last_transaction_id)->toBe($transaction->id)
        ->and($subscription->last_paid_at?->toDateTimeString())->toBe('2026-04-01 10:00:00')
        ->and($subscription->next_reminder_at?->toDateTimeString())->toBe('2027-03-02 10:00:00');

    $freshUser = $user->fresh();

    expect($freshUser->plan_code)->toBe('free')
        ->and($freshUser->subscription_status)->toBe('active')
        ->and($freshUser->subscription_ends_at)->toBeNull();
});

it('extends supporter validity by one year from the current end date', function () {
    $user = User::factory()->create();

    $this->billingSupportService->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-donation-3',
        'provider_event_id' => 'evt-kofi-3',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '10.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $firstEndsAt = $user->fresh()->billingSubscription->ends_at;

    Date::setTestNow(Date::parse('2026-07-01 09:30:00'));

    $this->billingSupportService->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-donation-4',
        'provider_event_id' => 'evt-kofi-4',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '15.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $subscription = $user->fresh()->billingSubscription;

    expect($subscription->started_at?->toDateTimeString())->toBe('2026-04-01 10:00:00')
        ->and($subscription->ends_at?->toDateTimeString())->toBe($firstEndsAt?->addYear()->toDateTimeString())
        ->and($subscription->last_paid_at?->toDateTimeString())->toBe('2026-07-01 09:30:00')
        ->and($subscription->next_reminder_at?->toDateTimeString())->toBe('2028-03-02 10:00:00');
});

it('marks the reminder as due when the support window is getting old', function () {
    $user = User::factory()->create();

    $this->billingSupportService->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-donation-reminder',
        'provider_event_id' => 'evt-kofi-reminder',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '10.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    Date::setTestNow(Date::parse('2027-03-03 10:00:00'));

    expect($user->fresh()->billingSubscription->reminderIsDue())->toBeTrue()
        ->and($user->fresh()->billingSubscription->hasActiveSupportWindow())->toBeTrue();
});

it('keeps the full billing transaction history when supporter access is renewed', function () {
    $user = User::factory()->create();

    $firstTransaction = $this->billingSupportService->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-donation-5',
        'provider_event_id' => 'evt-kofi-5',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '7.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    Date::setTestNow(Date::parse('2026-10-01 08:00:00'));

    $secondTransaction = $this->billingSupportService->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-donation-6',
        'provider_event_id' => 'evt-kofi-6',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '7.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $subscription = $user->fresh()->billingSubscription;
    $transactions = BillingTransaction::query()
        ->whereBelongsTo($user)
        ->orderBy('id')
        ->get();

    expect($transactions)->toHaveCount(2)
        ->and($transactions->pluck('id')->all())->toBe([$firstTransaction->id, $secondTransaction->id])
        ->and($transactions->pluck('provider_transaction_id')->all())->toBe(['kofi-donation-5', 'kofi-donation-6'])
        ->and($transactions->every(fn (BillingTransaction $transaction): bool => $transaction->billing_subscription_id === $subscription->id))->toBeTrue();
});

it('supports reconciling a pending donation to a user later on', function () {
    $user = User::factory()->create();

    $transaction = $this->billingSupportService->createDonationTransaction(
        BillingPlan::supporter(),
        [
            'provider' => BillingProviderEnum::Kofi,
            'provider_transaction_id' => 'kofi-donation-7',
            'provider_event_id' => 'evt-kofi-7',
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'currency' => 'EUR',
            'amount' => '20.00',
            'status' => BillingTransactionStatusEnum::Paid,
            'paid_at' => Date::now(),
            'received_at' => Date::now(),
            'raw_payload' => ['email' => $user->email],
        ],
    );

    $subscription = $this->billingSupportService->reconcileDonationToUser($transaction, $user);
    $transaction = $transaction->fresh();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->billing_subscription_id)->toBe($subscription->id)
        ->and($transaction->reconciliation_status)->toBe(BillingReconciliationStatusEnum::Reconciled)
        ->and($transaction->reconciled_at)->not()->toBeNull()
        ->and($subscription->is_supporter)->toBeTrue()
        ->and($subscription->billingPlan->code)->toBe('supporter');
});
