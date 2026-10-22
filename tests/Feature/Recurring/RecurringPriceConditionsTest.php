<?php

use App\Enums\MembershipSourceEnum;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\CompoundRecurringEntry;
use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    $this->priceUser = User::factory()->create();
    $this->priceAccount = createTestAccount($this->priceUser);
    $this->priceEntry = RecurringEntry::create([
        'user_id' => $this->priceUser->id, 'account_id' => $this->priceAccount->id,
        'title' => 'Abbonamento', 'entry_type' => 'recurring', 'direction' => 'expense',
        'expected_amount' => 10, 'currency' => 'EUR', 'start_date' => '2026-01-01',
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'never',
        'status' => 'active', 'is_active' => true,
    ]);
    $row = $this->priceEntry->occurrences()->create([
        'sequence_number' => 1, 'expected_date' => '2026-10-01', 'due_date' => '2026-10-01',
        'expected_amount' => 10, 'status' => 'completed',
    ]);
    $payment = Transaction::create([
        'user_id' => $this->priceUser->id, 'account_id' => $this->priceAccount->id,
        'transaction_date' => '2026-10-01', 'amount' => 10, 'currency' => 'EUR',
        'direction' => 'expense', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
    ]);
    $row->paymentAllocations()->create(['transaction_id' => $payment->id, 'amount_cents' => 1000]);
    $this->actingAs($this->priceUser);
    $this->priceUrl = route('recurring-entries.price-conditions.update', $this->priceEntry->uuid);
});

test('price conditions can be changed repeatedly without changing economic records', function (bool $variable) {
    $this->priceEntry->update(['is_amount_variable' => $variable]);
    $before = $this->priceEntry->fresh()->getAttributes();
    unset($before['price_conditions'], $before['updated_at']);
    $rows = $this->priceEntry->occurrences()->get()->map->getAttributes()->all();
    $payments = Transaction::all()->map->getAttributes()->all();
    $allocations = $this->priceEntry->occurrences()->first()->paymentAllocations()->get()->map->getAttributes()->all();
    $account = $this->priceAccount->fresh()->getAttributes();
    foreach ([
        ['type' => 'announced', 'new_amount' => '12.99', 'effective_from' => '2026-01-01'],
        ['type' => 'locked_until', 'locked_until' => '2000-01-01'],
        ['type' => 'locked'],
        ['type' => 'announced', 'new_amount' => '8.00', 'effective_from' => null],
        ['type' => 'standard'],
        ['locked_until' => '2027-12-31', 'new_amount' => '12.99', 'effective_from' => '2028-01-01'],
        ['new_amount' => '8.00'],
        ['locked_until' => '2026-01-01'],
        ['new_amount' => '0'],
        ['lock_mode' => 'indefinite', 'personal_price' => '10.00', 'change_scope' => 'personal', 'change_direction' => 'increase', 'new_amount' => '15.00'],
        ['lock_mode' => 'until', 'locked_until' => '2027-01-01', 'personal_price' => '10.00', 'change_scope' => 'personal', 'change_direction' => 'decrease', 'new_amount' => '8.00'],
        ['list_price' => '20.00', 'personal_price' => '10.00', 'locked_until' => '2027-12-31', 'change_scope' => 'list_only', 'new_list_price' => '25.00', 'effective_from' => '2027-01-01'],
        ['list_price' => '20.00', 'personal_price' => '10.00', 'locked_until' => '2027-12-31', 'change_scope' => 'personal', 'new_list_price' => '25.00', 'new_amount' => '15.00'],
        ['locked_until' => null, 'new_amount' => null, 'effective_from' => null],
    ] as $conditions) {
        $this->patch($this->priceUrl, [...$conditions, 'expected_amount' => 999, 'status' => 'cancelled'])
            ->assertSessionHasNoErrors()->assertRedirect(route('recurring-entries.show', $this->priceEntry->uuid));
        $expected = isset($conditions['type']) ? $conditions : array_filter($conditions, fn ($value): bool => $value !== null);
        $expected = $expected === [] ? null : $expected;
        expect($this->priceEntry->fresh()->price_conditions)->toEqual($expected);
        $this->getJson(route('recurring-entries.show', $this->priceEntry->uuid))->assertSuccessful()
            ->assertJsonPath('recurringEntry.price_conditions', fn ($value): bool => $value == $expected);
        $after = $this->priceEntry->fresh()->getAttributes();
        unset($after['price_conditions'], $after['updated_at']);
        expect($after)->toBe($before)
            ->and($this->priceEntry->occurrences()->get()->map->getAttributes()->all())->toBe($rows)
            ->and(Transaction::all()->map->getAttributes()->all())->toBe($payments)
            ->and($this->priceEntry->occurrences()->first()->paymentAllocations()->get()->map->getAttributes()->all())->toBe($allocations)
            ->and($this->priceAccount->fresh()->getAttributes())->toBe($account)
            ->and($this->priceEntry->amountChanges()->count())->toBe(0);
    }
})->with(['fixed' => false, 'variable' => true]);

test('only fields relevant to the selected condition are saved', function () {
    $this->patch($this->priceUrl, [
        'type' => 'locked', 'locked_until' => 'invalid', 'new_amount' => 'invalid', 'effective_from' => 'invalid',
    ])->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual(['type' => 'locked']);
});

test('invalid descriptive conditions are rejected', function (array $data, string $field) {
    $this->patch($this->priceUrl, $data)->assertSessionHasErrors($field);
    expect($this->priceEntry->fresh()->price_conditions)->toBeNull();
})->with([
    'unknown type' => [['type' => 'automatic'], 'type'],
    'missing expiry' => [['type' => 'locked_until'], 'locked_until'],
    'invalid expiry' => [['type' => 'locked_until', 'locked_until' => '2026-02-30'], 'locked_until'],
    'missing amount' => [['type' => 'announced'], 'new_amount'],
    'negative amount' => [['type' => 'announced', 'new_amount' => -1], 'new_amount'],
    'precision' => [['type' => 'announced', 'new_amount' => '10.001'], 'new_amount'],
    'invalid application date' => [['type' => 'announced', 'new_amount' => 12, 'effective_from' => 'bad'], 'effective_from'],
]);

test('announced changes accept zero and an omitted application date', function () {
    $this->patch($this->priceUrl, ['type' => 'announced', 'new_amount' => '0'])->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual(['type' => 'announced', 'new_amount' => '0']);
});

test('foreign users and read-only members cannot edit price conditions', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->patch($this->priceUrl, ['type' => 'locked'])->assertNotFound();
    AccountMembership::create([
        'account_id' => $this->priceAccount->id, 'user_id' => $viewer->id, 'role' => 'viewer',
        'status' => 'active', 'source' => MembershipSourceEnum::DIRECT,
        'granted_by_user_id' => $this->priceUser->id, 'joined_at' => now(),
    ]);
    $this->getJson(route('recurring-entries.show', $this->priceEntry->uuid))->assertSuccessful()
        ->assertJsonPath('recurringEntry.price_conditions', null);
    $this->patch($this->priceUrl, ['type' => 'locked'])->assertForbidden();
    expect($this->priceEntry->fresh()->price_conditions)->toBeNull();
});

test('installments and archived recurring components cannot edit descriptive conditions', function () {
    $this->priceEntry->update(['entry_type' => 'installment']);
    $this->patch($this->priceUrl, ['type' => 'locked'])->assertNotFound();
    $this->priceEntry->update(['entry_type' => 'recurring']);
    $category = Category::create(['user_id' => $this->priceUser->id, 'name' => 'Servizi', 'slug' => 'servizi', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $parent = CompoundRecurringEntry::factory()->create(['user_id' => $this->priceUser->id, 'account_id' => $this->priceAccount->id, 'category_id' => $category->id]);
    $component = $parent->components()->create(['name' => 'Componente', 'recurring_entry_id' => $this->priceEntry->id]);
    $this->patch($this->priceUrl, ['type' => 'locked'])->assertSessionHasNoErrors();
    $component->forceFill(['archived_at' => now()])->save();
    $this->patch($this->priceUrl, ['type' => 'standard'])->assertForbidden();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual(['type' => 'locked']);
});

test('both descriptive conditions can be independently updated and removed', function () {
    foreach ([
        ['locked_until' => '2027-01-01', 'new_amount' => '15.00', 'effective_from' => '2027-02-01'],
        ['locked_until' => null, 'new_amount' => '12.00', 'effective_from' => null],
        ['locked_until' => '2026-01-01', 'new_amount' => null, 'effective_from' => null],
        ['locked_until' => null, 'new_amount' => null, 'effective_from' => null],
    ] as $data) {
        $this->patch($this->priceUrl, $data)->assertSessionHasNoErrors();
        $expected = array_filter($data, fn ($value): bool => $value !== null);
        expect($this->priceEntry->fresh()->price_conditions)->toEqual($expected === [] ? null : $expected);
    }
});

test('existing legacy conditions remain readable and can be replaced without migrations', function (array $legacy) {
    $this->priceEntry->forceFill(['price_conditions' => $legacy])->save();
    $this->getJson(route('recurring-entries.show', $this->priceEntry->uuid))->assertSuccessful()
        ->assertJsonPath('recurringEntry.price_conditions', $legacy);
    expect($this->priceEntry->fresh()->price_conditions)->toEqual($legacy);
    $data = ['locked_until' => '2027-01-01', 'new_amount' => '15.00'];
    $this->patch($this->priceUrl, $data)->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual($data);
})->with([
    'standard' => [['type' => 'standard']],
    'indefinite lock' => [['type' => 'locked']],
    'dated lock' => [['type' => 'locked_until', 'locked_until' => '2026-12-01']],
    'announced' => [['type' => 'announced', 'new_amount' => '12.99', 'effective_from' => null]],
]);

test('new format validates dates and amounts without requiring a legacy type', function (array $data, string $field) {
    $this->patch($this->priceUrl, $data)->assertSessionHasErrors($field);
    expect($this->priceEntry->fresh()->price_conditions)->toBeNull();
})->with([
    'invalid lock' => [['locked_until' => 'bad'], 'locked_until'],
    'negative amount' => [['new_amount' => -1], 'new_amount'],
    'precision' => [['new_amount' => '10.001'], 'new_amount'],
    'invalid date' => [['new_amount' => 12, 'effective_from' => 'bad'], 'effective_from'],
]);

test('a supplier-only change cannot introduce a personal change and can later be edited', function () {
    $data = ['list_price' => '20.00', 'personal_price' => '10.00', 'change_scope' => 'list_only', 'new_list_price' => '25.00', 'effective_from' => '2027-01-01'];
    $this->patch($this->priceUrl, [...$data, 'new_amount' => 999])->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual($data);
    $next = [...$data, 'change_scope' => 'personal', 'new_amount' => '15.00'];
    $this->patch($this->priceUrl, $next)->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual($next)
        ->and((float) $this->priceEntry->fresh()->expected_amount)->toBe(10.0);
});

test('descriptive supplier and personal prices validate scope dates and amounts', function (array $data, string $field) {
    $this->patch($this->priceUrl, $data)->assertSessionHasErrors($field);
    expect($this->priceEntry->fresh()->price_conditions)->toBeNull();
})->with([
    'negative list' => [['list_price' => -1], 'list_price'],
    'personal precision' => [['personal_price' => '10.001'], 'personal_price'],
    'unknown scope' => [['change_scope' => 'automatic'], 'change_scope'],
    'missing supplier change' => [['change_scope' => 'list_only'], 'new_list_price'],
    'missing personal change' => [['change_scope' => 'personal'], 'new_amount'],
    'invalid new list' => [['change_scope' => 'list_only', 'new_list_price' => -1], 'new_list_price'],
    'invalid date' => [['change_scope' => 'list_only', 'new_list_price' => 20, 'effective_from' => 'bad'], 'effective_from'],
]);

test('guided changes validate direction against the correct known previous price', function (array $data, string $field) {
    $this->patch($this->priceUrl, $data)->assertSessionHasErrors($field);
    expect($this->priceEntry->fresh()->price_conditions)->toBeNull();
})->with([
    'increase is lower' => [['change_scope' => 'personal', 'change_direction' => 'increase', 'new_amount' => 9], 'new_amount'],
    'decrease is higher' => [['change_scope' => 'personal', 'change_direction' => 'decrease', 'new_amount' => 11], 'new_amount'],
    'unchanged price' => [['change_scope' => 'personal', 'change_direction' => 'increase', 'new_amount' => 10], 'new_amount'],
    'service price' => [['list_price' => 20, 'change_scope' => 'list_only', 'change_direction' => 'increase', 'new_list_price' => 15], 'new_list_price'],
    'explicit previous price' => [['personal_price' => 20, 'change_scope' => 'personal', 'change_direction' => 'decrease', 'new_amount' => 25], 'new_amount'],
    'unknown direction' => [['change_direction' => 'automatic'], 'change_direction'],
    'missing scope' => [['change_direction' => 'increase', 'new_amount' => 15], 'change_scope'],
    'missing guarantee date' => [['lock_mode' => 'until'], 'locked_until'],
    'unknown guarantee' => [['lock_mode' => 'automatic'], 'lock_mode'],
]);

test('an indefinite guarantee can coexist with a service change and be removed independently', function () {
    $data = ['lock_mode' => 'indefinite', 'personal_price' => 10, 'change_scope' => 'list_only', 'change_direction' => 'increase', 'new_list_price' => 20];
    $this->patch($this->priceUrl, [...$data, 'locked_until' => 'bad'])->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual($data);
    $this->patch($this->priceUrl, ['personal_price' => 10, 'lock_mode' => 'indefinite'])->assertSessionHasNoErrors();
    expect($this->priceEntry->fresh()->price_conditions)->toEqual(['personal_price' => 10, 'lock_mode' => 'indefinite']);
});

test('service price notes use the effective date and the actual occurrence due date without changing records', function (string $expectedDate, ?string $dueDate, string $expectedAmount, ?float $servicePrice) {
    $this->priceEntry->forceFill(['price_conditions' => ['change_scope' => 'list_only', 'list_price' => '11.99', 'new_list_price' => '12.99', 'effective_from' => '2026-10-15']])->save();
    $row = $this->priceEntry->occurrences()->create([
        'sequence_number' => 2, 'expected_date' => $expectedDate, 'due_date' => $dueDate,
        'expected_amount' => $expectedAmount, 'status' => 'pending',
    ]);
    $entryBefore = $this->priceEntry->fresh()->getAttributes();
    $rowBefore = $row->fresh()->getAttributes();
    $paymentsBefore = Transaction::all()->map->getAttributes()->all();
    $accountBefore = $this->priceAccount->fresh()->getAttributes();
    $payload = $this->getJson(route('recurring-entries.show', $this->priceEntry->uuid))->assertSuccessful()->json();
    $presented = collect($payload['recurringEntry']['occurrences'])->firstWhere('uuid', $row->uuid);
    expect($presented['service_price_at_due_date'])->toBe($servicePrice)
        ->and((float) $presented['expected_amount'])->toBe((float) $expectedAmount)
        ->and($this->priceEntry->fresh()->getAttributes())->toBe($entryBefore)
        ->and($row->fresh()->getAttributes())->toBe($rowBefore)
        ->and(Transaction::all()->map->getAttributes()->all())->toBe($paymentsBefore)
        ->and($this->priceAccount->fresh()->getAttributes())->toBe($accountBefore);
    $chunk = $this->getJson(route('recurring-entries.show', [$this->priceEntry->uuid, 'occurrence_year' => 2026, 'occurrence_offset' => 0]))->assertSuccessful()->json();
    expect(collect($chunk['occurrences'])->firstWhere('uuid', $row->uuid)['service_price_at_due_date'])->toBe($servicePrice);
})->with([
    'before' => ['2026-10-14', '2026-10-14', '9.99', null],
    'effective day' => ['2026-10-15', '2026-10-15', '9.99', 12.99],
    'after' => ['2026-10-20', '2026-10-20', '9.99', 12.99],
    'date fallback' => ['2026-10-20', null, '9.99', 12.99],
    'later override' => ['2026-10-01', '2026-10-20', '9.99', 12.99],
    'earlier override' => ['2026-10-20', '2026-10-01', '9.99', null],
    'no real difference' => ['2026-10-20', '2026-10-20', '12.99', null],
]);

test('service price notes omit unknown dates prices and changes affecting the user', function (array $conditions) {
    $this->priceEntry->forceFill(['price_conditions' => $conditions])->save();
    $this->getJson(route('recurring-entries.show', $this->priceEntry->uuid))->assertSuccessful()
        ->assertJsonPath('recurringEntry.occurrences.0.service_price_at_due_date', null);
})->with([
    'no conditions' => [[]],
    'no effective date' => [['change_scope' => 'list_only', 'new_list_price' => 12.99]],
    'invalid date' => [['change_scope' => 'list_only', 'new_list_price' => 12.99, 'effective_from' => '2026-02-30']],
    'unknown price' => [['change_scope' => 'list_only', 'effective_from' => '2026-01-01']],
    'only previous price' => [['change_scope' => 'list_only', 'list_price' => 12.99, 'effective_from' => '2026-01-01']],
    'personal change' => [['change_scope' => 'personal', 'new_list_price' => 12.99, 'new_amount' => 11.99, 'effective_from' => '2026-01-01']],
    'legacy announcement' => [['type' => 'announced', 'new_amount' => 12.99, 'effective_from' => '2026-01-01']],
]);

test('compound notes use the price conditions of each occurrence owner', function () {
    $this->priceEntry->forceFill(['price_conditions' => ['change_scope' => 'list_only', 'new_list_price' => 12.99, 'effective_from' => '2026-01-01']])->save();
    $other = $this->priceEntry->replicate(['uuid']);
    $other->forceFill(['price_conditions' => ['change_scope' => 'list_only', 'new_list_price' => 19.99, 'effective_from' => '2026-01-01']])->save();
    $otherRow = $other->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-01', 'expected_amount' => 10, 'status' => 'pending']);
    $category = Category::create(['user_id' => $this->priceUser->id, 'name' => 'Servizi', 'slug' => 'servizi', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $parent = CompoundRecurringEntry::factory()->create(['user_id' => $this->priceUser->id, 'account_id' => $this->priceAccount->id, 'category_id' => $category->id]);
    $parent->components()->create(['name' => 'Primo', 'recurring_entry_id' => $this->priceEntry->id]);
    $parent->components()->create(['name' => 'Secondo', 'recurring_entry_id' => $other->id]);
    $rows = collect($this->getJson(route('recurring-entries.show', $parent->uuid))->assertSuccessful()->json('recurringEntry.occurrences'));
    expect($rows->firstWhere('uuid', $otherRow->uuid)['service_price_at_due_date'])->toBe(19.99)
        ->and($rows->firstWhere('entry_uuid', $this->priceEntry->uuid)['service_price_at_due_date'])->toBe(12.99);
});
