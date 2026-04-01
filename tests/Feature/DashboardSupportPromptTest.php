<?php

use App\Enums\BillingProviderEnum;
use App\Enums\BillingTransactionStatusEnum;
use App\Models\User;
use App\Services\Billing\BillingSupportService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(BillingPlanSeeder::class);

    Date::setTestNow(Date::parse('2026-04-01 10:00:00'));
});

afterEach(function () {
    Date::setTestNow();
});

test('dashboard shows the ko-fi widget for users who never donated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('support_prompt.show_kofi_widget', true)
            ->where('support_prompt.support_prompt_variant', 'first_support')
            ->where('support_prompt.support_state', 'never_donated')
            ->where('support_prompt.kofi_widget.page_id', 'M4M61X1IRC'));
});

test('dashboard hides the ko-fi widget for users with recent active support', function () {
    $user = User::factory()->create();

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-dashboard-recent-1',
        'provider_event_id' => 'evt-dashboard-recent-1',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '10.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('support_prompt.show_kofi_widget', false)
            ->where('support_prompt.support_prompt_variant', null)
            ->where('support_prompt.support_state', 'support_recent'));
});

test('dashboard uses renew support prompt when the reminder is due', function () {
    $user = User::factory()->create();

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-dashboard-reminder-1',
        'provider_event_id' => 'evt-dashboard-reminder-1',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '10.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now()->subMonths(11),
        'received_at' => Date::now()->subMonths(11),
    ]);

    $user->billingSubscription()->firstOrFail()->forceFill([
        'status' => 'supporting',
        'is_supporter' => true,
        'ends_at' => Date::now()->addMonth(),
        'next_reminder_at' => Date::now()->subDay(),
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('support_prompt.show_kofi_widget', true)
            ->where('support_prompt.support_prompt_variant', 'renew_support')
            ->where('support_prompt.support_state', 'reminder_due'));
});

test('dashboard uses support again prompt for lapsed supporters', function () {
    $user = User::factory()->create();

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'kofi-dashboard-lapsed-1',
        'provider_event_id' => 'evt-dashboard-lapsed-1',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '10.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now()->subYears(2),
        'received_at' => Date::now()->subYears(2),
    ]);

    $user->billingSubscription()->firstOrFail()->forceFill([
        'status' => 'inactive',
        'is_supporter' => false,
        'ends_at' => Date::now()->subMonth(),
        'next_reminder_at' => Date::now()->addMonth(),
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('support_prompt.show_kofi_widget', true)
            ->where('support_prompt.support_prompt_variant', 'support_again')
            ->where('support_prompt.support_state', 'support_lapsed'));
});

test('dashboard copy includes ko-fi support variants in italian and english', function () {
    $messages = file_get_contents(resource_path('js/i18n/messages/dashboard.ts'));

    expect($messages)->toContain('first_support')
        ->toContain('renew_support')
        ->toContain('support_again')
        ->toContain('Offrimi un Ko-fi')
        ->toContain('Rinnova il supporto')
        ->toContain('Dona di nuovo')
        ->toContain('Support me on Ko-fi')
        ->toContain('Renew support')
        ->toContain('Donate again');
});

test('ko-fi widget loader uses a singleton script guard and controlled draw mount', function () {
    $loader = file_get_contents(resource_path('js/lib/kofi-widget.ts'));

    expect($loader)->toContain('__soamcoKofiWidgetLoader')
        ->toContain('KOFI_SCRIPT_ID')
        ->toContain('document.writeln = writeToHost')
        ->toContain('widget.draw()');
});

test('dashboard partial reload requests support prompt together with dashboard data', function () {
    $dashboardPage = file_get_contents(resource_path('js/pages/Dashboard.vue'));

    expect($dashboardPage)->toContain("only: ['dashboard', 'support_prompt']");
});
