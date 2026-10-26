<?php

use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\ScheduledEntry;
use App\Models\TrackedItem;
use App\Models\User;
use App\Models\UserYear;
use App\Models\WebsiteIdentity;
use App\Services\Dashboard\DashboardService;

it('includes existing reference logos in arrears upcoming payments and payee boxes', function (string $source) {
    $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
    config(['features.credits_debts.enabled' => true]);
    $user = User::factory()->create();
    UserYear::create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
    $account = createTestAccount($user);
    $reference = TrackedItem::create(['user_id' => $user->id, 'name' => 'Allianz', 'slug' => 'allianz', 'is_active' => true,
        'settings' => $source === 'custom' ? ['reference_logo_path' => 'references/allianz.png'] : [],
        'website_identity_id' => $source === 'website' ? WebsiteIdentity::create(['domain' => 'allianz.example', 'canonical_url' => 'https://allianz.example', 'status' => 'ready', 'logo_path' => 'logos/allianz.png'])->id : null,
    ]);
    $entry = RecurringEntry::create(['user_id' => $user->id, 'account_id' => $account->id, 'tracked_item_id' => $reference->id,
        'title' => 'Insurance', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring',
        'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-01', 'expected_amount' => 50,
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'never', 'auto_create_transaction' => false]);
    $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-01', 'expected_amount' => 50, 'status' => 'pending']);
    $entry->occurrences()->create(['sequence_number' => 2, 'expected_date' => '2026-10-27', 'expected_amount' => 50, 'status' => 'pending']);
    ScheduledEntry::create(['user_id' => $user->id, 'account_id' => $account->id, 'tracked_item_id' => $reference->id,
        'title' => 'Planned insurance', 'direction' => 'expense', 'currency' => 'EUR', 'expected_amount' => 20, 'scheduled_date' => '2026-10-28', 'status' => 'planned']);
    CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $reference->id, 'type' => 'credit', 'total_amount' => 10, 'due_date' => '2026-10-02']);
    userTransaction($user, $account, ['kind' => 'manual', 'tracked_item_id' => $reference->id, 'transaction_date' => '2026-10-25', 'amount' => 30]);
    $before = TrackedItem::count();
    $expected = $reference->logoUrl();
    $data = app(DashboardService::class)->build($user, 2026, 10);
    expect($data['credits_debts']['arrears'])->toHaveCount(2);
    foreach ($data['credits_debts']['arrears'] as $item) {
        expect($item)->toHaveKey('logo_url', $expected);
    }
    expect($data['scheduled_summary']['upcoming'])->toHaveCount(2);
    foreach ($data['scheduled_summary']['upcoming'] as $item) {
        expect($item)->toHaveKey('logo_url', $expected);
    }
    expect($data['merchant_breakdown'][0])->toHaveKey('logo_url', $expected)
        ->and($data['merchant_breakdown'][0]['total_amount_raw'])->toBe(30.0)
        ->and(TrackedItem::count())->toBe($before);
    expect($data['pending_actions']['items'])->toHaveCount(3);
    foreach ($data['pending_actions']['items'] as $item) {
        expect($item)->toHaveKey('logo_url', $expected);
    }
})->with(['custom', 'website', 'none']);
