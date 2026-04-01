<?php

namespace App\Services\Billing;

use App\Models\BillingTransaction;
use App\Models\User;

class ProfileSupportSummaryService
{
    public function __construct(
        protected DashboardSupportPromptService $dashboardSupportPromptService,
    ) {}

    /**
     * @return array{
     *     support_state: string,
     *     last_donation_at: ?string,
     *     next_reminder_at: ?string,
     *     donations_count: int,
     *     history: array<int, array{
     *         id: int,
     *         provider: string,
     *         amount: string,
     *         currency: string,
     *         status: string,
     *         paid_at: ?string
     *     }>,
     *     show_kofi_widget: bool,
     *     support_prompt_variant: ?string,
     *     kofi_widget: array{
     *         script_url: string,
     *         page_id: string,
     *         button_color: string
     *     }
     * }
     */
    public function forUser(User $user): array
    {
        $user->loadMissing('billingSubscription');

        $history = $user->billingTransactions()
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BillingTransaction $transaction): array => [
                'id' => $transaction->id,
                'provider' => $transaction->provider->value,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status->value,
                'paid_at' => $transaction->paid_at?->toIso8601String(),
            ])
            ->all();

        $latestTransaction = $user->billingTransactions()
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();
        $supportPrompt = $this->dashboardSupportPromptService->forUser($user);

        return [
            'support_state' => $supportPrompt['support_state'],
            'last_donation_at' => $latestTransaction?->paid_at?->toIso8601String(),
            'next_reminder_at' => $user->billingSubscription?->next_reminder_at?->toIso8601String(),
            'donations_count' => count($history),
            'history' => $history,
            'show_kofi_widget' => $supportPrompt['show_kofi_widget'],
            'support_prompt_variant' => $supportPrompt['support_prompt_variant'],
            'kofi_widget' => $supportPrompt['kofi_widget'],
        ];
    }
}
