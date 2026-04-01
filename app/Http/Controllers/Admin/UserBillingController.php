<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBillingTransactionRequest;
use App\Http\Requests\Admin\UpdateBillingSubscriptionRequest;
use App\Http\Requests\Admin\UpdateBillingTransactionRequest;
use App\Models\BillingTransaction;
use App\Models\User;
use App\Services\Admin\AdminUserBillingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserBillingController extends Controller
{
    public function __construct(
        protected AdminUserBillingService $adminUserBillingService,
    ) {}

    public function show(User $user): Response
    {
        return Inertia::render('admin/UserBilling', [
            'user' => $this->adminUserBillingService->userPayload($user),
            'transactions' => $this->adminUserBillingService->transactionHistory($user),
            'plans' => $this->adminUserBillingService->planOptions(),
            'providers' => $this->adminUserBillingService->providerOptions(),
            'supportStates' => $this->adminUserBillingService->supportStateOptions(),
            'availableTransactions' => $this->adminUserBillingService->availableTransactions($user),
        ]);
    }

    public function storeTransaction(StoreBillingTransactionRequest $request, User $user): RedirectResponse
    {
        $this->adminUserBillingService->storeTransaction(
            $request->user(),
            $user,
            $request->validated(),
        );

        return back()->with('success', __('admin.users.billing.flash.transaction_saved'));
    }

    public function updateTransaction(
        UpdateBillingTransactionRequest $request,
        User $user,
        BillingTransaction $billingTransaction,
    ): RedirectResponse {
        $this->adminUserBillingService->updateTransaction(
            $request->user(),
            $user,
            $billingTransaction,
            $request->validated(),
        );

        return back()->with('success', __('admin.users.billing.flash.transaction_updated'));
    }

    public function assignTransaction(
        UpdateBillingTransactionRequest $request,
        User $user,
        BillingTransaction $billingTransaction,
    ): RedirectResponse {
        $this->adminUserBillingService->assignTransaction(
            $request->user(),
            $user,
            $billingTransaction,
        );

        return back()->with('success', __('admin.users.billing.flash.transaction_assigned'));
    }

    public function updateSubscription(UpdateBillingSubscriptionRequest $request, User $user): RedirectResponse
    {
        $this->adminUserBillingService->updateSubscription(
            $request->user(),
            $user,
            $request->validated(),
        );

        return back()->with('success', __('admin.users.billing.flash.support_updated'));
    }

    public function destroySubscription(User $user): RedirectResponse
    {
        $this->adminUserBillingService->destroySubscription($user);

        return back()->with('success', __('admin.users.billing.flash.subscription_deleted'));
    }
}
