<?php

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Models\BillingTransaction;
use App\Models\User;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(BillingPlanSeeder::class);

    config()->set('services.kofi.webhook_verification_token', 'test-kofi-token');
});

test('token valido accetta la request del webhook', function () {
    postKofiWebhook([
        'message_id' => 'msg-valid-token-1',
    ])
        ->assertOk()
        ->assertJson([
            'status' => 'processed',
        ]);
});

test('token invalido rifiuta la request del webhook', function () {
    postKofiWebhook([
        'verification_token' => 'wrong-token',
        'message_id' => 'msg-invalid-token-1',
    ])
        ->assertUnauthorized()
        ->assertJson([
            'status' => 'invalid_token',
        ]);

    expect(BillingTransaction::query()->count())->toBe(0);
});

test('payload form urlencoded con data json viene parsato correttamente', function () {
    postKofiWebhook([
        'message_id' => 'msg-form-urlencoded-1',
        'type' => 'Donation',
        'amount' => '3.00',
        'currency' => 'usd',
    ])->assertOk();

    $transaction = kofiTransaction('msg-form-urlencoded-1');

    expect($transaction->currency)->toBe('USD')
        ->and($transaction->metadata['type'])->toBe('Donation');
});

test('payload json malformato viene rifiutato in modo sicuro', function () {
    test()->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->post(route('webhooks.kofi'), [
            'data' => '{"verification_token":"test-kofi-token"',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'status' => 'invalid_payload',
        ]);

    expect(BillingTransaction::query()->count())->toBe(0);
});

test('donation viene salvata correttamente', function () {
    postKofiWebhook([
        'message_id' => 'msg-donation-1',
        'kofi_transaction_id' => 'kofi-donation-1',
        'type' => 'Donation',
        'from_name' => 'Ko-fi Supporter',
        'email' => 'donor@example.com',
        'amount' => '5.50',
        'currency' => 'EUR',
        'timestamp' => '2026-04-01T11:30:00Z',
        'is_public' => true,
    ])->assertOk();

    $transaction = kofiTransaction('msg-donation-1');

    expect($transaction->provider)->toBe(BillingProviderEnum::Kofi)
        ->and($transaction->provider_transaction_id)->toBe('kofi-donation-1')
        ->and($transaction->customer_email)->toBe('donor@example.com')
        ->and($transaction->customer_name)->toBe('Ko-fi Supporter')
        ->and($transaction->amount)->toBe('5.50')
        ->and($transaction->status->value)->toBe('paid')
        ->and($transaction->metadata['type'])->toBe('Donation');
});

test('subscription con is subscription payment true viene salvata correttamente', function () {
    postKofiWebhook([
        'message_id' => 'msg-subscription-1',
        'type' => 'Subscription',
        'is_subscription_payment' => true,
        'tier_name' => 'Gold Supporter',
    ])->assertOk();

    $transaction = kofiTransaction('msg-subscription-1');

    expect($transaction->is_recurring)->toBeTrue()
        ->and($transaction->metadata['type'])->toBe('Subscription')
        ->and($transaction->metadata['tier_name'])->toBe('Gold Supporter')
        ->and($transaction->metadata['is_subscription_payment'])->toBeTrue();
});

test('is first subscription payment true viene salvato correttamente', function () {
    postKofiWebhook([
        'message_id' => 'msg-first-subscription-1',
        'type' => 'Subscription',
        'is_subscription_payment' => true,
        'is_first_subscription_payment' => true,
    ])->assertOk();

    expect(kofiTransaction('msg-first-subscription-1')->metadata['is_first_subscription_payment'])->toBeTrue();
});

test('shop order salva gli shop items', function () {
    postKofiWebhook([
        'message_id' => 'msg-shop-order-1',
        'type' => 'Shop Order',
        'shop_items' => [
            ['direct_link_code' => 'ebook-001', 'variation_name' => 'PDF'],
            ['direct_link_code' => 'bundle-002', 'variation_name' => 'Full'],
        ],
    ])->assertOk();

    $transaction = kofiTransaction('msg-shop-order-1');

    expect($transaction->metadata['type'])->toBe('Shop Order')
        ->and($transaction->metadata['shop_items'])->toHaveCount(2);
});

test('commission viene salvata in modo coerente senza attivare support window', function () {
    $user = User::factory()->create([
        'email' => 'commission@example.com',
    ]);

    postKofiWebhook([
        'message_id' => 'msg-commission-1',
        'type' => 'Commission',
        'email' => $user->email,
    ])->assertOk();

    $transaction = kofiTransaction('msg-commission-1');

    expect($transaction->metadata['type'])->toBe('Commission')
        ->and($transaction->user_id)->toBe($user->id)
        ->and($user->fresh()->billingSubscription)->toBeNull();
});

test('is public false viene salvato coerentemente', function () {
    postKofiWebhook([
        'message_id' => 'msg-private-1',
        'is_public' => false,
    ])->assertOk();

    expect(kofiTransaction('msg-private-1')->metadata['is_public'])->toBeFalse();
});

test('retry con stesso message id non duplica', function () {
    postKofiWebhook([
        'message_id' => 'msg-duplicate-1',
        'kofi_transaction_id' => 'kofi-duplicate-1',
    ])->assertOk();

    postKofiWebhook([
        'message_id' => 'msg-duplicate-1',
        'kofi_transaction_id' => 'kofi-duplicate-1',
    ])
        ->assertOk()
        ->assertJson([
            'status' => 'duplicate',
        ]);

    expect(BillingTransaction::query()->count())->toBe(1);
});

test('transazione non riconciliata resta tracciata', function () {
    postKofiWebhook([
        'message_id' => 'msg-unmatched-1',
        'email' => 'missing-user@example.com',
    ])->assertOk();

    $transaction = kofiTransaction('msg-unmatched-1');

    expect($transaction->user_id)->toBeNull()
        ->and($transaction->reconciliation_status)->toBe(BillingReconciliationStatusEnum::Pending);
});

test('matching immediato per email riconcilia la donation ad un utente esistente', function () {
    $user = User::factory()->create([
        'email' => 'matched@example.com',
    ]);

    postKofiWebhook([
        'message_id' => 'msg-matched-1',
        'type' => 'Donation',
        'email' => $user->email,
        'from_name' => $user->name,
        'kofi_transaction_id' => 'kofi-matched-1',
    ])->assertOk();

    $transaction = kofiTransaction('msg-matched-1');

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->reconciliation_status)->toBe(BillingReconciliationStatusEnum::Reconciled)
        ->and($user->fresh()->billingSubscription)->not->toBeNull();
});

test('display name da solo non genera un match automatico senza email esatta', function () {
    $user = User::factory()->create([
        'email' => 'matched@example.com',
        'name' => 'Ko-fi Supporter',
    ]);

    postKofiWebhook([
        'message_id' => 'msg-name-only-1',
        'type' => 'Donation',
        'email' => 'different@example.com',
        'from_name' => $user->name,
        'kofi_transaction_id' => 'kofi-name-only-1',
    ])->assertOk();

    $transaction = kofiTransaction('msg-name-only-1');

    expect($transaction->user_id)->toBeNull()
        ->and($transaction->reconciliation_status)->toBe(BillingReconciliationStatusEnum::Pending)
        ->and($user->fresh()->billingSubscription)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function postKofiWebhook(array $overrides = []): TestResponse
{
    $payload = array_merge([
        'verification_token' => 'test-kofi-token',
        'message_id' => 'msg-default',
        'kofi_transaction_id' => 'txn-default',
        'timestamp' => '2026-04-01T11:00:00Z',
        'type' => 'Donation',
        'from_name' => 'Ko-fi Team',
        'message' => 'Thanks for your work',
        'amount' => '3.00',
        'currency' => 'USD',
        'url' => 'https://ko-fi.com/example',
        'email' => 'supporter@example.com',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
        'is_public' => true,
        'tier_name' => null,
        'shop_items' => [],
    ], $overrides);

    return test()->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->post(route('webhooks.kofi'), [
            'data' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
}

function kofiTransaction(string $messageId): BillingTransaction
{
    return BillingTransaction::query()
        ->where('provider', BillingProviderEnum::Kofi)
        ->where('provider_event_id', $messageId)
        ->firstOrFail();
}
