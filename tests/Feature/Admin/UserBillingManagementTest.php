<?php

use App\Enums\BillingProviderEnum;
use App\Enums\BillingSubscriptionStatusEnum;
use App\Enums\BillingTransactionStatusEnum;
use App\Models\BillingPlan;
use App\Models\User;
use App\Services\Billing\BillingSupportService;
use Database\Seeders\BillingPlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(BillingPlanSeeder::class);

    Date::setTestNow(Date::parse('2026-04-01 10:00:00'));
});

afterEach(function () {
    Date::setTestNow();
});

test('admin can view support history and support status for a user', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create(['email' => 'supporter@example.com']);
    $target->assignRole('user');

    app(BillingSupportService::class)->recordSupporterDonation($target, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-admin-view-1',
        'provider_event_id' => 'evt-admin-view-1',
        'customer_email' => $target->email,
        'customer_name' => $target->name,
        'currency' => 'EUR',
        'amount' => '12.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    expect(route('admin.users.billing.show', $target))->toContain('/users/'.$target->uuid.'/billing');

    $this->actingAs($admin)
        ->get('/admin/users/'.$target->uuid.'/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/UserBilling')
            ->where('user.email', 'supporter@example.com')
            ->where('user.uuid', $target->uuid)
            ->where('user.plan_code', 'free')
            ->where('user.donations_count', 1)
            ->where('transactions.0.provider', 'kofi'));
});

test('admin can register a manual donation for a user', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/transactions', [
            'billing_plan_code' => 'supporter',
            'provider' => 'manual',
            'provider_transaction_id' => 'manual-1',
            'provider_event_id' => 'manual-event-1',
            'customer_email' => $target->email,
            'customer_name' => $target->name,
            'currency' => 'EUR',
            'amount' => '8.50',
            'status' => 'paid',
            'paid_at' => Date::now()->toDateTimeString(),
            'received_at' => Date::now()->toDateTimeString(),
            'is_recurring' => false,
            'apply_support_window' => true,
            'admin_notes' => 'Manual support import',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription = $target->fresh()->billingSubscription;

    expect($target->billingTransactions()->count())->toBe(1)
        ->and($subscription->status)->toBe(BillingSubscriptionStatusEnum::Supporting)
        ->and($subscription->admin_notes)->toBeNull()
        ->and($target->fresh()->plan_code)->toBe('free');
});

test('admin can update an existing donation', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $transaction = app(BillingSupportService::class)->recordSupporterDonation($target, [
        'provider' => BillingProviderEnum::Manual,
        'provider_transaction_id' => 'manual-edit-1',
        'provider_event_id' => 'manual-edit-event-1',
        'customer_email' => $target->email,
        'customer_name' => $target->name,
        'currency' => 'EUR',
        'amount' => '5.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/transactions/'.$transaction->id, [
            '_method' => 'PATCH',
            'amount' => '11.50',
            'admin_notes' => 'Corrected amount',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($transaction->fresh()->amount)->toBe('11.50')
        ->and($transaction->fresh()->admin_notes)->toBe('Corrected amount');
});

test('admin can associate an unreconciled donation to a user', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create(['email' => 'target@example.com']);
    $target->assignRole('user');

    $transaction = app(BillingSupportService::class)->createDonationTransaction(
        BillingPlan::supporter(),
        [
            'provider' => BillingProviderEnum::Kofi,
            'provider_transaction_id' => 'kofi-pending-1',
            'provider_event_id' => 'kofi-pending-event-1',
            'customer_email' => 'target@example.com',
            'customer_name' => $target->name,
            'currency' => 'EUR',
            'amount' => '17.00',
            'status' => BillingTransactionStatusEnum::Paid,
            'paid_at' => Date::now(),
            'received_at' => Date::now(),
        ],
    );

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/transactions/'.$transaction->id.'/assign', [
            '_method' => 'PATCH',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($transaction->fresh()->user_id)->toBe($target->id)
        ->and($target->fresh()->billingSubscription->is_supporter)->toBeTrue();
});

test('admin can update the non blocking support window manually', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/subscription', [
            '_method' => 'PATCH',
            'status' => 'inactive',
            'billing_plan_code' => 'supporter',
            'is_supporter' => false,
            'started_at' => '2026-01-01 00:00:00',
            'ends_at' => '2026-12-31 23:59:59',
            'next_reminder_at' => '2026-12-01 09:00:00',
            'admin_notes' => 'Manual review complete',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription = $target->fresh()->billingSubscription;

    expect($subscription->status)->toBe(BillingSubscriptionStatusEnum::Inactive)
        ->and($subscription->is_supporter)->toBeFalse()
        ->and($subscription->ends_at?->toDateTimeString())->toBe('2026-12-31 23:59:59')
        ->and($target->fresh()->plan_code)->toBe('free');
});

test('admin can update the support subscription with direct patch requests', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $this->actingAs($admin)
        ->patch('/admin/users/'.$target->uuid.'/billing/subscription', [
            'status' => 'supporting',
            'billing_plan_code' => 'supporter',
            'is_supporter' => true,
            'started_at' => '2026-04-01 10:00:00',
            'ends_at' => '2027-04-01 10:00:00',
            'next_reminder_at' => '2027-03-02 10:00:00',
            'admin_notes' => 'Direct patch update',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->billingSubscription?->status)->toBe(BillingSubscriptionStatusEnum::Supporting)
        ->and($target->fresh()->billingSubscription?->is_supporter)->toBeTrue();
});

test('admin can clear a support subscription back to free', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    app(BillingSupportService::class)->recordSupporterDonation($target, [
        'provider' => BillingProviderEnum::Manual,
        'provider_transaction_id' => 'manual-clear-1',
        'provider_event_id' => 'manual-clear-event-1',
        'customer_email' => $target->email,
        'customer_name' => $target->name,
        'currency' => 'EUR',
        'amount' => '15.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/subscription', [
            '_method' => 'PATCH',
            'status' => 'free',
            'billing_plan_code' => 'free',
            'is_supporter' => false,
            'started_at' => null,
            'ends_at' => null,
            'next_reminder_at' => null,
            'admin_notes' => 'Reset to free',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription = $target->fresh()->billingSubscription;

    expect($subscription?->status)->toBe(BillingSubscriptionStatusEnum::Free)
        ->and($subscription?->is_supporter)->toBeFalse()
        ->and($subscription?->started_at)->toBeNull()
        ->and($subscription?->ends_at)->toBeNull()
        ->and($target->fresh()->plan_code)->toBe('free');
});

test('admin can delete an erroneous support subscription without losing donation history', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    app(BillingSupportService::class)->recordSupporterDonation($target, [
        'provider' => BillingProviderEnum::Manual,
        'provider_transaction_id' => 'manual-delete-1',
        'provider_event_id' => 'manual-delete-event-1',
        'customer_email' => $target->email,
        'customer_name' => $target->name,
        'currency' => 'EUR',
        'amount' => '15.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/subscription', [
            '_method' => 'DELETE',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->billingSubscription)->toBeNull()
        ->and($target->fresh()->billingTransactions()->count())->toBe(1)
        ->and($target->fresh()->plan_code)->toBe('free')
        ->and($target->fresh()->subscription_status)->toBe('active');
});

test('billing form endpoints resolve correctly with user uuid paths', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $transaction = app(BillingSupportService::class)->recordSupporterDonation($target, [
        'provider' => BillingProviderEnum::Manual,
        'provider_transaction_id' => 'manual-form-path-1',
        'provider_event_id' => 'manual-form-path-event-1',
        'customer_email' => $target->email,
        'customer_name' => $target->name,
        'currency' => 'EUR',
        'amount' => '9.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/users/'.$target->uuid.'/billing')
        ->assertOk();

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/transactions/'.$transaction->id, [
            '_method' => 'PATCH',
            'amount' => '10.00',
            'admin_notes' => 'Updated through uuid endpoint',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post('/admin/users/'.$target->uuid.'/billing/subscription', [
            '_method' => 'PATCH',
            'status' => 'supporting',
            'billing_plan_code' => 'supporter',
            'is_supporter' => true,
            'started_at' => '2026-04-01 10:00:00',
            'ends_at' => '2027-04-01 10:00:00',
            'next_reminder_at' => '2027-03-02 10:00:00',
            'admin_notes' => 'UUID support update',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($transaction->fresh()->amount)->toBe('10.00')
        ->and($target->fresh()->billingSubscription?->status)->toBe(BillingSubscriptionStatusEnum::Supporting);
});
