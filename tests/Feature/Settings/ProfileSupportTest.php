<?php

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Enums\BillingTransactionStatusEnum;
use App\Models\BillingPlan;
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

test('utente con donazioni vede lo storico nel profilo', function () {
    $user = User::factory()->create();

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'profile-history-1',
        'provider_event_id' => 'profile-history-event-1',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '8.50',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now()->subDays(3),
        'received_at' => Date::now()->subDays(3),
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('support.donations_count', 1)
            ->where('support.history.0.provider', 'kofi')
            ->where('support.history.0.amount', '8.50')
            ->where('support.history.0.currency', 'EUR'));
});

test('utente senza donazioni vede stato vuoto corretto e widget kofi', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('support.donations_count', 0)
            ->where('support.history', [])
            ->where('support.support_state', 'never_donated')
            ->where('support.show_kofi_widget', true)
            ->where('support.support_prompt_variant', 'first_support'));
});

test('widget kofi compare nel profilo quando il reminder e dovuto', function () {
    $user = User::factory()->create();

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'profile-reminder-1',
        'provider_event_id' => 'profile-reminder-event-1',
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
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('support.show_kofi_widget', true)
            ->where('support.support_prompt_variant', 'renew_support')
            ->where('support.support_state', 'reminder_due'));
});

test('widget kofi non compare nel profilo quando il supporto e recente', function () {
    $user = User::factory()->create();

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'profile-recent-1',
        'provider_event_id' => 'profile-recent-event-1',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '12.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now(),
        'received_at' => Date::now(),
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('support.show_kofi_widget', false)
            ->where('support.support_prompt_variant', null)
            ->where('support.support_state', 'support_recent'));
});

test('il profilo mostra solo le donazioni effettivamente associate all utente', function () {
    $user = User::factory()->create([
        'email' => 'profile-match@example.com',
    ]);

    app(BillingSupportService::class)->recordSupporterDonation($user, [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'profile-associated-1',
        'provider_event_id' => 'profile-associated-event-1',
        'customer_email' => $user->email,
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '9.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now()->subDay(),
        'received_at' => Date::now()->subDay(),
    ]);

    app(BillingSupportService::class)->createDonationTransaction(BillingPlan::supporter(), [
        'provider' => BillingProviderEnum::Kofi,
        'provider_transaction_id' => 'profile-unmatched-1',
        'provider_event_id' => 'profile-unmatched-event-1',
        'customer_email' => 'different@example.com',
        'customer_name' => $user->name,
        'currency' => 'EUR',
        'amount' => '4.00',
        'status' => BillingTransactionStatusEnum::Paid,
        'paid_at' => Date::now()->subHours(2),
        'received_at' => Date::now()->subHours(2),
        'reconciliation_status' => BillingReconciliationStatusEnum::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('support.donations_count', 1)
            ->where('support.history', fn ($history) => count($history) === 1
                && $history[0]['provider'] === 'kofi'
                && $history[0]['amount'] === '9.00'));
});

test('copy it en e riuso del widget kofi sono presenti nel profilo', function () {
    $settingsMessages = file_get_contents(resource_path('js/i18n/messages/settings.ts'));
    $dashboardMessages = file_get_contents(resource_path('js/i18n/messages/dashboard.ts'));
    $profilePage = file_get_contents(resource_path('js/pages/settings/Profile.vue'));
    $kofiWidget = file_get_contents(resource_path('js/components/support/KofiSupportWidget.vue'));

    expect($settingsMessages)->toContain("title: 'Donazioni'")
        ->toContain("title: 'Support'")
        ->toContain("title: 'Sostieni il progetto'")
        ->toContain("description:\n                                'Non hai ancora effettuato alcuna donazione.'")
        ->toContain("title: 'Support the project'")
        ->toContain("description:\n                                'You haven’t made any donations yet.'")
        ->toContain("note: 'Per associare correttamente la donazione al tuo profilo, usa su Ko-fi la stessa email del tuo account.'")
        ->toContain("note: 'To link your donation to your profile, please use the same email on Ko-fi as your account email.'")
        ->toContain('Mai donato')
        ->toContain('Never donated')
        ->and($dashboardMessages)->toContain("note: 'Per associare correttamente la donazione al tuo profilo, usa su Ko-fi la stessa email del tuo account.'")
        ->toContain("note: 'To link your donation to your profile, please use the same email on Ko-fi as your account email.'")
        ->and($profilePage)->toContain('KofiSupportWidget')
        ->toContain('settings.profile.support.prompt.variants.')
        ->toContain('supportPromptCopy.note')
        ->toContain('dashboard.supportPrompt.variants.')
        ->not->toContain('settings.profile.support.summary.nextReminder')
        ->and($kofiWidget)->toContain('watch(')
        ->toContain('props.buttonLabel')
        ->toContain('await renderWidget()');
});

test('la sezione profilo non mostra il prossimo promemoria', function () {
    $profilePage = file_get_contents(resource_path('js/pages/settings/Profile.vue'));
    $settingsMessages = file_get_contents(resource_path('js/i18n/messages/settings.ts'));

    expect($profilePage)->not->toContain('settings.profile.support.summary.nextReminder')
        ->and($settingsMessages)->not->toContain("nextReminder: 'Prossimo promemoria'")
        ->not->toContain("nextReminder: 'Next reminder'");
});
