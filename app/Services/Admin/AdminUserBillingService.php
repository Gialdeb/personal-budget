<?php

namespace App\Services\Admin;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Enums\BillingSubscriptionStatusEnum;
use App\Models\BillingPlan;
use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use App\Models\User;
use App\Services\Billing\BillingSupportService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminUserBillingService
{
    public function __construct(
        protected BillingSupportService $billingSupportService,
    ) {}

    public function userPayload(User $user): array
    {
        $user->loadMissing([
            'billingSubscription.billingPlan',
            'billingSubscription.lastTransaction',
        ]);

        $subscription = $user->billingSubscription;
        $donationsCount = $user->billingTransactions()->count();

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'surname' => $user->surname,
            'full_name' => trim(collect([$user->name, $user->surname])->filter()->implode(' ')),
            'email' => $user->email,
            'plan_code' => $user->plan_code,
            'support_plan_code' => $subscription?->billingPlan?->code,
            'support_status' => $subscription?->status?->value ?? BillingSubscriptionStatusEnum::Free->value,
            'support_state_label' => $this->supportStateLabel($subscription, $donationsCount),
            'is_supporter' => $subscription?->is_supporter ?? false,
            'support_started_at' => $subscription?->started_at?->toIso8601String(),
            'support_window_ends_at' => $subscription?->ends_at?->toIso8601String(),
            'last_contribution_at' => $subscription?->last_paid_at?->toIso8601String(),
            'next_support_reminder_at' => $subscription?->next_reminder_at?->toIso8601String(),
            'admin_notes' => $subscription?->admin_notes,
            'donations_count' => $donationsCount,
        ];
    }

    public function transactionHistory(User $user): array
    {
        return $user->billingTransactions()
            ->with('billingPlan:id,code,name')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BillingTransaction $transaction): array => [
                'id' => $transaction->id,
                'provider' => $transaction->provider->value,
                'provider_transaction_id' => $transaction->provider_transaction_id,
                'provider_event_id' => $transaction->provider_event_id,
                'billing_plan_code' => $transaction->billingPlan?->code,
                'customer_email' => $transaction->customer_email,
                'customer_name' => $transaction->customer_name,
                'currency' => $transaction->currency,
                'amount' => $transaction->amount,
                'status' => $transaction->status->value,
                'paid_at' => $transaction->paid_at?->toIso8601String(),
                'received_at' => $transaction->received_at?->toIso8601String(),
                'is_recurring' => $transaction->is_recurring,
                'reconciliation_status' => $transaction->reconciliation_status->value,
                'reconciled_at' => $transaction->reconciled_at?->toIso8601String(),
                'admin_notes' => $transaction->admin_notes,
            ])->all();
    }

    public function availableTransactions(User $user): array
    {
        return BillingTransaction::query()
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')
                    ->orWhere(function ($nestedQuery) use ($user): void {
                        $nestedQuery->where('user_id', '!=', $user->id)
                            ->where('customer_email', $user->email);
                    });
            })
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->map(fn (BillingTransaction $transaction): array => [
                'id' => $transaction->id,
                'provider' => $transaction->provider->value,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'customer_email' => $transaction->customer_email,
                'status' => $transaction->status->value,
                'received_at' => $transaction->received_at?->toIso8601String(),
            ])->all();
    }

    public function planOptions(): array
    {
        BillingPlan::ensureDefaults();

        return BillingPlan::query()
            ->orderBy('id')
            ->get(['code', 'name'])
            ->map(fn (BillingPlan $plan): array => [
                'value' => $plan->code,
                'label' => $plan->name,
            ])->all();
    }

    public function providerOptions(): array
    {
        return collect(BillingProviderEnum::cases())
            ->map(fn (BillingProviderEnum $provider): array => [
                'value' => $provider->value,
                'label' => ucfirst($provider->value),
            ])->all();
    }

    public function supportStateOptions(): array
    {
        return collect(BillingSubscriptionStatusEnum::cases())
            ->map(fn (BillingSubscriptionStatusEnum $status): array => [
                'value' => $status->value,
                'label' => __("admin.users.billing.supportStatuses.{$status->value}"),
            ])->all();
    }

    public function storeTransaction(User $admin, User $user, array $attributes): BillingTransaction
    {
        $plan = $this->planByCode($attributes['billing_plan_code']);

        $payload = [
            'provider' => $attributes['provider'],
            'provider_transaction_id' => $attributes['provider_transaction_id'] ?? null,
            'provider_event_id' => $attributes['provider_event_id'] ?? null,
            'customer_email' => $attributes['customer_email'] ?? $user->email,
            'customer_name' => $attributes['customer_name'] ?? trim(collect([$user->name, $user->surname])->filter()->implode(' ')),
            'currency' => strtoupper((string) $attributes['currency']),
            'amount' => $attributes['amount'],
            'status' => $attributes['status'],
            'paid_at' => $attributes['paid_at'] ?? null,
            'received_at' => $attributes['received_at'] ?? null,
            'is_recurring' => (bool) $attributes['is_recurring'],
            'admin_notes' => $attributes['admin_notes'] ?? null,
            'metadata' => [
                'admin_recorded_by_user_id' => $admin->id,
            ],
        ];

        if ((bool) $attributes['apply_support_window'] && $plan->grantsSupporterAccess()) {
            return $this->billingSupportService->recordSupporterDonation($user, $payload, $plan);
        }

        return $this->billingSupportService->createDonationTransaction($plan, [
            ...$payload,
            'user_id' => $user->id,
            'reconciliation_status' => BillingReconciliationStatusEnum::Reconciled,
        ]);
    }

    public function updateTransaction(User $admin, User $user, BillingTransaction $billingTransaction, array $attributes): BillingTransaction
    {
        if ($billingTransaction->user_id !== $user->id) {
            throw new InvalidArgumentException('The selected billing transaction does not belong to the target user.');
        }

        $billingTransaction->fill([
            ...Arr::only($attributes, [
                'provider',
                'provider_transaction_id',
                'provider_event_id',
                'customer_email',
                'customer_name',
                'amount',
                'status',
                'paid_at',
                'received_at',
                'is_recurring',
                'admin_notes',
            ]),
            'currency' => array_key_exists('currency', $attributes)
                ? strtoupper((string) $attributes['currency'])
                : $billingTransaction->currency,
            'metadata' => [
                ...($billingTransaction->metadata ?? []),
                'admin_updated_by_user_id' => $admin->id,
            ],
        ]);

        $billingTransaction->save();

        return $billingTransaction->refresh();
    }

    public function assignTransaction(User $admin, User $user, BillingTransaction $billingTransaction): BillingTransaction
    {
        DB::transaction(function () use ($admin, $billingTransaction, $user): void {
            $this->billingSupportService->reconcileDonationToUser($billingTransaction, $user);

            $billingTransaction->forceFill([
                'admin_notes' => trim(collect([
                    $billingTransaction->admin_notes,
                    'Assigned by admin #'.$admin->id,
                ])->filter()->implode("\n")),
            ])->save();
        });

        return $billingTransaction->refresh();
    }

    public function updateSubscription(User $admin, User $user, array $attributes): BillingSubscription
    {
        $subscription = $this->billingSupportService->ensureFreeSubscription($user);
        $plan = $this->planByCode($attributes['billing_plan_code']);
        $status = BillingSubscriptionStatusEnum::from($attributes['status']);

        $subscription->fill([
            'billing_plan_id' => $plan->id,
            'status' => $status,
            'provider' => $subscription->provider ?? BillingProviderEnum::Manual,
            'is_supporter' => (bool) $attributes['is_supporter'],
            'started_at' => $attributes['started_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'next_reminder_at' => $attributes['next_reminder_at'] ?? null,
            'admin_notes' => trim(collect([
                $attributes['admin_notes'] ?? null,
                'Updated by admin #'.$admin->id,
            ])->filter()->implode("\n")),
        ]);

        if ($status === BillingSubscriptionStatusEnum::Free) {
            $subscription->forceFill([
                'billing_plan_id' => BillingPlan::free()->id,
                'is_supporter' => false,
                'started_at' => null,
                'ends_at' => null,
            ]);
        }

        $subscription->save();

        $user->forceFill([
            'plan_code' => BillingPlan::CODE_FREE,
            'subscription_status' => 'active',
            'subscription_started_at' => null,
            'subscription_ends_at' => null,
        ])->save();

        return $subscription->refresh();
    }

    public function destroySubscription(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->billingSubscription()?->delete();

            $user->forceFill([
                'plan_code' => BillingPlan::CODE_FREE,
                'subscription_status' => 'active',
                'subscription_started_at' => null,
                'subscription_ends_at' => null,
            ])->save();
        });
    }

    protected function planByCode(string $code): BillingPlan
    {
        BillingPlan::ensureDefaults();

        return BillingPlan::query()->where('code', $code)->firstOrFail();
    }

    protected function supportStateLabel(?BillingSubscription $subscription, int $donationsCount): string
    {
        if (! $subscription instanceof BillingSubscription || $donationsCount === 0) {
            return __('admin.users.support.states.never_donated');
        }

        if ($subscription->reminderIsDue()) {
            return __('admin.users.support.states.reminder_due');
        }

        if ($subscription->hasActiveSupportWindow()) {
            return __('admin.users.support.states.support_recent');
        }

        return __('admin.users.support.states.support_lapsed');
    }
}
