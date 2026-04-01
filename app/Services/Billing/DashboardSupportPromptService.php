<?php

namespace App\Services\Billing;

use App\Models\BillingSubscription;
use App\Models\User;

class DashboardSupportPromptService
{
    /**
     * @return array{
     *     show_kofi_widget: bool,
     *     support_prompt_variant: ?string,
     *     support_state: string,
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

        $subscription = $user->billingSubscription;
        $donationsCount = $user->billingTransactions()->count();
        $supportState = $this->supportState($subscription, $donationsCount);
        $variant = $this->variantForState($supportState);
        $hasKofiConfiguration = filled(config('services.kofi.page_id'))
            && filled(config('services.kofi.script_url'));

        return [
            'show_kofi_widget' => (bool) config('services.kofi.enabled', false)
                && $hasKofiConfiguration
                && $variant !== null,
            'support_prompt_variant' => $variant,
            'support_state' => $supportState,
            'kofi_widget' => [
                'script_url' => (string) config('services.kofi.script_url'),
                'page_id' => (string) config('services.kofi.page_id'),
                'button_color' => (string) config('services.kofi.button_color', '#f59273'),
            ],
        ];
    }

    protected function supportState(?BillingSubscription $subscription, int $donationsCount): string
    {
        if ($donationsCount === 0) {
            return 'never_donated';
        }

        if (! $subscription instanceof BillingSubscription) {
            return 'support_lapsed';
        }

        return match (true) {
            $subscription->reminderIsDue() => 'reminder_due',
            $subscription->hasActiveSupportWindow() => 'support_recent',
            default => 'support_lapsed',
        };
    }

    protected function variantForState(string $supportState): ?string
    {
        return match ($supportState) {
            'never_donated' => 'first_support',
            'reminder_due' => 'renew_support',
            'support_lapsed' => 'support_again',
            default => null,
        };
    }
}
