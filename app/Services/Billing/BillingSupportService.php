<?php

namespace App\Services\Billing;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Enums\BillingSubscriptionStatusEnum;
use App\Enums\BillingTransactionStatusEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Models\BillingPlan;
use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingSupportService
{
    /**
     * @param  array{
     *     provider: BillingProviderEnum|string,
     *     provider_transaction_id?: ?string,
     *     provider_event_id?: ?string,
     *     customer_email?: ?string,
     *     customer_name?: ?string,
     *     currency?: string,
     *     amount: int|float|string,
     *     status?: BillingTransactionStatusEnum|string,
     *     paid_at?: ?CarbonInterface|string,
     *     received_at?: ?CarbonInterface|string,
     *     is_recurring?: bool,
     *     raw_payload?: ?array,
     *     metadata?: ?array,
     *     admin_notes?: ?string,
     *     user_id?: ?int,
     *     reconciliation_status?: BillingReconciliationStatusEnum|string
     * }  $attributes
     */
    public function createDonationTransaction(BillingPlan $plan, array $attributes): BillingTransaction
    {
        $reconciliationStatus = $attributes['reconciliation_status']
            ?? (($attributes['user_id'] ?? null) !== null
                ? BillingReconciliationStatusEnum::Reconciled
                : BillingReconciliationStatusEnum::Pending);

        return BillingTransaction::query()->create([
            'user_id' => $attributes['user_id'] ?? null,
            'billing_plan_id' => $plan->id,
            'provider' => $this->providerValue($attributes['provider']),
            'provider_transaction_id' => $attributes['provider_transaction_id'] ?? null,
            'provider_event_id' => $attributes['provider_event_id'] ?? null,
            'customer_email' => $attributes['customer_email'] ?? null,
            'customer_name' => $attributes['customer_name'] ?? null,
            'currency' => strtoupper($attributes['currency'] ?? 'EUR'),
            'amount' => $attributes['amount'],
            'status' => $this->transactionStatusValue($attributes['status'] ?? BillingTransactionStatusEnum::Paid),
            'paid_at' => $attributes['paid_at'] ?? null,
            'received_at' => $attributes['received_at'] ?? null,
            'is_recurring' => $attributes['is_recurring'] ?? false,
            'reconciliation_status' => $this->reconciliationStatusValue($reconciliationStatus),
            'raw_payload' => $attributes['raw_payload'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'admin_notes' => $attributes['admin_notes'] ?? null,
        ]);
    }

    /**
     * @param  array{
     *     provider: BillingProviderEnum|string,
     *     provider_transaction_id?: ?string,
     *     provider_event_id?: ?string,
     *     customer_email?: ?string,
     *     customer_name?: ?string,
     *     currency?: string,
     *     amount: int|float|string,
     *     status?: BillingTransactionStatusEnum|string,
     *     paid_at?: ?CarbonInterface|string,
     *     received_at?: ?CarbonInterface|string,
     *     is_recurring?: bool,
     *     raw_payload?: ?array,
     *     metadata?: ?array,
     *     admin_notes?: ?string
     * }  $attributes
     */
    public function recordSupporterDonation(User $user, array $attributes, ?BillingPlan $plan = null): BillingTransaction
    {
        $plan ??= BillingPlan::supporter();

        return DB::transaction(function () use ($attributes, $plan, $user): BillingTransaction {
            $transaction = $this->createDonationTransaction($plan, $attributes);

            $this->reconcileDonationToUser($transaction, $user, $plan);

            return $transaction->refresh();
        });
    }

    public function reconcileDonationToUser(BillingTransaction $transaction, User $user, ?BillingPlan $plan = null): BillingSubscription
    {
        if ($transaction->status !== BillingTransactionStatusEnum::Paid) {
            throw new InvalidArgumentException('Only paid billing transactions can grant supporter access.');
        }

        $plan ??= $transaction->billingPlan ?? BillingPlan::supporter();

        if (! $plan->grantsSupporterAccess()) {
            throw new InvalidArgumentException('The selected billing plan does not grant supporter access.');
        }

        return DB::transaction(function () use ($plan, $transaction, $user): BillingSubscription {
            $subscription = BillingSubscription::query()->firstOrNew([
                'user_id' => $user->id,
            ]);

            $paidAt = $this->effectivePaidAt($transaction);
            $isCurrentlyActive = $subscription->exists
                && $subscription->ends_at !== null
                && $subscription->ends_at->isFuture();

            $startsAt = $isCurrentlyActive
                ? ($subscription->started_at ?? $paidAt)
                : $paidAt;

            $extensionAnchor = $isCurrentlyActive
                ? $subscription->ends_at
                : $paidAt;

            $endsAt = $plan->extendFrom($extensionAnchor);

            $subscription->fill([
                'billing_plan_id' => $plan->id,
                'status' => BillingSubscriptionStatusEnum::Supporting,
                'provider' => $transaction->provider,
                'is_supporter' => true,
                'started_at' => $startsAt,
                'ends_at' => $endsAt,
                'last_transaction_id' => $transaction->id,
                'last_paid_at' => $paidAt,
                'next_reminder_at' => $plan->reminderAt($endsAt),
            ]);

            $subscription->save();

            $transaction->forceFill([
                'user_id' => $user->id,
                'billing_plan_id' => $plan->id,
                'billing_subscription_id' => $subscription->id,
                'reconciliation_status' => BillingReconciliationStatusEnum::Reconciled,
                'reconciled_at' => now(),
            ])->save();

            $subscription->forceFill([
                'last_transaction_id' => $transaction->id,
            ])->save();

            $this->syncLegacyUserSnapshot($user, $subscription->refresh()->loadMissing('billingPlan'));

            return $subscription->refresh();
        });
    }

    public function ensureFreeSubscription(User $user): BillingSubscription
    {
        $existingSubscription = BillingSubscription::query()
            ->whereBelongsTo($user)
            ->first();

        if ($existingSubscription !== null) {
            return $existingSubscription;
        }

        $freePlan = BillingPlan::free();

        $subscription = BillingSubscription::query()->create([
            'user_id' => $user->id,
            'billing_plan_id' => $freePlan->id,
            'status' => BillingSubscriptionStatusEnum::Free,
            'provider' => BillingProviderEnum::Manual,
            'is_supporter' => false,
            'started_at' => null,
            'ends_at' => null,
            'last_transaction_id' => null,
            'last_paid_at' => null,
            'next_reminder_at' => null,
            'admin_notes' => null,
        ]);

        $this->syncLegacyUserSnapshot($user, $subscription->loadMissing('billingPlan'));

        return $subscription;
    }

    protected function effectivePaidAt(BillingTransaction $transaction): CarbonInterface
    {
        return $transaction->paid_at
            ?? $transaction->received_at
            ?? now();
    }

    protected function syncLegacyUserSnapshot(User $user, BillingSubscription $subscription): void
    {
        $user->forceFill([
            'plan_code' => BillingPlan::CODE_FREE,
            'subscription_status' => SubscriptionStatusEnum::ACTIVE->value,
            'subscription_started_at' => null,
            'subscription_ends_at' => null,
        ])->save();
    }

    protected function providerValue(BillingProviderEnum|string $provider): BillingProviderEnum
    {
        return $provider instanceof BillingProviderEnum
            ? $provider
            : BillingProviderEnum::from($provider);
    }

    protected function reconciliationStatusValue(BillingReconciliationStatusEnum|string $status): BillingReconciliationStatusEnum
    {
        return $status instanceof BillingReconciliationStatusEnum
            ? $status
            : BillingReconciliationStatusEnum::from($status);
    }

    protected function transactionStatusValue(BillingTransactionStatusEnum|string $status): BillingTransactionStatusEnum
    {
        return $status instanceof BillingTransactionStatusEnum
            ? $status
            : BillingTransactionStatusEnum::from($status);
    }
}
