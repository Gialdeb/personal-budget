<?php

namespace App\Services\Billing;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Enums\BillingTransactionStatusEnum;
use App\Models\BillingPlan;
use App\Models\BillingTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use JsonException;

class KofiWebhookService
{
    public function __construct(
        protected BillingSupportService $billingSupportService,
    ) {}

    /**
     * @return array{status: string, http_status: int, transaction_id?: int}
     */
    public function handle(Request $request): array
    {
        $configuredToken = (string) config('services.kofi.webhook_verification_token', '');

        if ($configuredToken === '') {
            Log::error('Ko-fi webhook token is not configured.');

            return [
                'status' => 'misconfigured',
                'http_status' => 503,
            ];
        }

        $rawData = $request->string('data')->toString();

        if ($rawData === '') {
            Log::warning('Ko-fi webhook payload is missing the data field.', [
                'content_type' => $request->header('Content-Type'),
            ]);

            return [
                'status' => 'invalid_payload',
                'http_status' => 422,
            ];
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('Ko-fi webhook payload contains invalid JSON.', [
                'content_type' => $request->header('Content-Type'),
            ]);

            return [
                'status' => 'invalid_payload',
                'http_status' => 422,
            ];
        }

        $providedToken = (string) Arr::get($payload, 'verification_token', '');

        if (! hash_equals($configuredToken, $providedToken)) {
            Log::warning('Ko-fi webhook verification failed.', [
                'message_id' => Arr::get($payload, 'message_id'),
                'ip' => $request->ip(),
            ]);

            return [
                'status' => 'invalid_token',
                'http_status' => 401,
            ];
        }

        $messageId = $this->nullableString(Arr::get($payload, 'message_id'));

        if ($messageId === null) {
            Log::warning('Ko-fi webhook payload is missing message_id.', [
                'ip' => $request->ip(),
            ]);

            return [
                'status' => 'invalid_payload',
                'http_status' => 422,
            ];
        }

        $existingTransaction = BillingTransaction::query()
            ->where('provider', BillingProviderEnum::Kofi)
            ->where('provider_event_id', $messageId)
            ->first();

        if ($existingTransaction !== null) {
            return [
                'status' => 'duplicate',
                'http_status' => 200,
                'transaction_id' => $existingTransaction->id,
            ];
        }

        $transaction = $this->storeWebhookTransaction($payload);

        return [
            'status' => 'processed',
            'http_status' => 200,
            'transaction_id' => $transaction->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeWebhookTransaction(array $payload): BillingTransaction
    {
        $email = $this->nullableString($payload['email'] ?? null);
        $user = $this->matchedUserForEmail($email);

        $type = $this->nullableString($payload['type'] ?? null) ?? 'Donation';
        $supportsSupportWindow = in_array($type, ['Donation', 'Subscription'], true);
        $transactionAttributes = [
            'provider' => BillingProviderEnum::Kofi,
            'provider_transaction_id' => $this->nullableString(
                $payload['kofi_transaction_id'] ?? $payload['transaction_id'] ?? null,
            ),
            'provider_event_id' => $this->nullableString($payload['message_id'] ?? null),
            'customer_email' => $email,
            'customer_name' => $this->nullableString($payload['from_name'] ?? null),
            'currency' => strtoupper((string) ($payload['currency'] ?? 'EUR')),
            'amount' => $payload['amount'] ?? '0.00',
            'status' => BillingTransactionStatusEnum::Paid,
            'paid_at' => $this->timestamp($payload['timestamp'] ?? null),
            'received_at' => now(),
            'is_recurring' => (bool) ($payload['is_subscription_payment'] ?? false),
            'raw_payload' => $payload,
            'metadata' => [
                'type' => $type,
                'is_public' => (bool) ($payload['is_public'] ?? true),
                'is_subscription_payment' => (bool) ($payload['is_subscription_payment'] ?? false),
                'is_first_subscription_payment' => (bool) ($payload['is_first_subscription_payment'] ?? false),
                'tier_name' => $this->nullableString($payload['tier_name'] ?? null),
                'shop_items' => is_array($payload['shop_items'] ?? null) ? $payload['shop_items'] : [],
            ],
            'reconciliation_status' => $user instanceof User
                ? BillingReconciliationStatusEnum::Reconciled
                : BillingReconciliationStatusEnum::Pending,
            'user_id' => $supportsSupportWindow ? null : $user?->id,
        ];

        if ($user instanceof User && $supportsSupportWindow) {
            return $this->billingSupportService->recordSupporterDonation(
                $user,
                $transactionAttributes,
                BillingPlan::supporter(),
            );
        }

        $plan = $supportsSupportWindow
            ? BillingPlan::supporter()
            : BillingPlan::free();

        return $this->billingSupportService->createDonationTransaction($plan, $transactionAttributes);
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    protected function timestamp(mixed $value): ?Carbon
    {
        $timestamp = $this->nullableString($value);

        if ($timestamp === null) {
            return null;
        }

        return Carbon::parse($timestamp);
    }

    protected function matchedUserForEmail(?string $email): ?User
    {
        if ($email === null) {
            return null;
        }

        $matches = User::query()
            ->where('email', $email)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
