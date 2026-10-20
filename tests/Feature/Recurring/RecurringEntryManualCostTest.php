<?php

use App\Enums\MembershipSourceEnum;
use App\Models\AccountMembership;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryManualCost;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->manualUser = User::factory()->create();
    $this->manualAccount = createTestAccount($this->manualUser);
    $this->manualEntry = RecurringEntry::query()->create([
        'user_id' => $this->manualUser->id, 'account_id' => $this->manualAccount->id,
        'title' => 'Costo informativo', 'entry_type' => 'recurring', 'direction' => 'expense',
        'expected_amount' => 100, 'currency' => 'EUR', 'is_amount_variable' => false,
        'start_date' => '2026-01-01', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'end_mode' => 'never', 'status' => 'active', 'is_active' => true,
    ]);
    $this->actingAs($this->manualUser);
    $this->manualUrl = route('recurring-entries.manual-costs.store', $this->manualEntry->uuid);
});

test('manual costs support historical CRUD without changing economic records', function (bool $variable) {
    $this->manualEntry->update(['is_amount_variable' => $variable]);
    $entryBefore = $this->manualEntry->fresh()->getAttributes();
    $accountBefore = $this->manualAccount->fresh()->getAttributes();
    $transactionsBefore = Transaction::count();
    $this->post($this->manualUrl, ['date' => '1999-02-15', 'amount' => '49.90'])
        ->assertSessionHasNoErrors()->assertRedirect(route('recurring-entries.show', $this->manualEntry->uuid));
    $cost = $this->manualEntry->manualCosts()->sole();
    expect($cost->currency)->toBe('EUR')->and($cost->date->toDateString())->toBe('1999-02-15');
    $this->getJson(route('recurring-entries.show', $this->manualEntry->uuid))->assertSuccessful()
        ->assertJsonPath('recurringEntry.manual_costs.0.uuid', $cost->uuid)
        ->assertJsonPath('recurringEntry.manual_costs.0.amount', 49.9);
    $params = [$this->manualEntry->uuid, $cost->uuid];
    $this->patch(route('recurring-entries.manual-costs.update', $params), ['date' => '2000-03-01', 'amount' => '55.50'])->assertSessionHasNoErrors();
    expect($cost->fresh()->amount)->toBe('55.50');
    $this->delete(route('recurring-entries.manual-costs.destroy', $params))->assertSessionHasNoErrors();
    $this->assertModelMissing($cost);
    expect($this->manualEntry->fresh()->getAttributes())->toBe($entryBefore)
        ->and($this->manualAccount->fresh()->getAttributes())->toBe($accountBefore)
        ->and($this->manualEntry->occurrences()->count())->toBe(0)
        ->and($this->manualEntry->amountChanges()->count())->toBe(0)
        ->and(Transaction::count())->toBe($transactionsBefore);
})->with(['fixed' => false, 'variable' => true]);

test('manual costs reject exact duplicates but retain distinct same-day costs', function () {
    $data = ['date' => '2000-01-01', 'amount' => '10.00'];
    $this->post($this->manualUrl, $data)->assertSessionHasNoErrors();
    $this->post($this->manualUrl, [...$data, 'amount' => '10'])->assertSessionHasErrors('amount');
    $this->post($this->manualUrl, [...$data, 'amount' => '11'])->assertSessionHasNoErrors();
    $costs = $this->manualEntry->manualCosts()->get();
    $this->patch(route('recurring-entries.manual-costs.update', [$this->manualEntry->uuid, $costs[1]->uuid]), $data)->assertSessionHasErrors('amount');
    $this->patch(route('recurring-entries.manual-costs.update', [$this->manualEntry->uuid, $costs[0]->uuid]), $data)->assertSessionHasNoErrors();
    expect($this->manualEntry->manualCosts()->count())->toBe(2);
});

test('manual costs validate only historical dates positive precise amounts and server-side currency', function (array $data, string $error) {
    $this->post($this->manualUrl, [...['date' => '2000-01-01', 'amount' => '10.00'], ...$data])->assertSessionHasErrors($error);
    expect($this->manualEntry->manualCosts()->count())->toBe(0);
})->with([
    'future' => [['date' => today()->addDay()->toDateString()], 'date'],
    'invalid date' => [['date' => '2000-02-31'], 'date'],
    'zero' => [['amount' => 0], 'amount'], 'negative' => [['amount' => -1], 'amount'],
    'precision' => [['amount' => '1.001'], 'amount'], 'currency' => [['currency' => 'USD'], 'currency'],
]);

test('manual costs reject foreign users cross-plan bindings and installment plans for every mutation', function () {
    $cost = $this->manualEntry->manualCosts()->save(RecurringEntryManualCost::factory()->make());
    $other = $this->manualEntry->replicate(['uuid']);
    $other->save();
    $this->patch(route('recurring-entries.manual-costs.update', [$other->uuid, $cost->uuid]), ['date' => '2000-01-01', 'amount' => 20])->assertNotFound();
    $this->delete(route('recurring-entries.manual-costs.destroy', [$other->uuid, $cost->uuid]))->assertNotFound();
    $this->actingAs(User::factory()->create());
    $this->post($this->manualUrl, ['date' => '2000-01-01', 'amount' => 20])->assertNotFound();
    $this->patch(route('recurring-entries.manual-costs.update', [$this->manualEntry->uuid, $cost->uuid]), ['date' => '2000-01-01', 'amount' => 20])->assertNotFound();
    $this->delete(route('recurring-entries.manual-costs.destroy', [$this->manualEntry->uuid, $cost->uuid]))->assertNotFound();
    $this->actingAs($this->manualUser);
    $this->manualEntry->update(['entry_type' => 'installment']);
    $this->post($this->manualUrl, ['date' => '2000-01-01', 'amount' => 20])->assertNotFound();
    $this->patch(route('recurring-entries.manual-costs.update', [$this->manualEntry->uuid, $cost->uuid]), ['date' => '2000-01-01', 'amount' => 20])->assertNotFound();
    $this->delete(route('recurring-entries.manual-costs.destroy', [$this->manualEntry->uuid, $cost->uuid]))->assertNotFound();
    $this->assertModelExists($cost);
});

test('read-only members can inspect historical costs but cannot mutate them', function () {
    $cost = $this->manualEntry->manualCosts()->save(RecurringEntryManualCost::factory()->make());
    $viewer = User::factory()->create();
    AccountMembership::query()->create([
        'account_id' => $this->manualAccount->id, 'user_id' => $viewer->id,
        'role' => 'viewer', 'status' => 'active', 'source' => MembershipSourceEnum::DIRECT,
        'granted_by_user_id' => $this->manualUser->id, 'joined_at' => now(),
    ]);
    $this->actingAs($viewer);
    $this->getJson(route('recurring-entries.show', $this->manualEntry->uuid))->assertSuccessful()
        ->assertJsonCount(1, 'recurringEntry.manual_costs');
    $this->post($this->manualUrl, ['date' => '2000-01-01', 'amount' => 20])->assertForbidden();
    $params = [$this->manualEntry->uuid, $cost->uuid];
    $this->patch(route('recurring-entries.manual-costs.update', $params), ['date' => '2000-01-01', 'amount' => 20])->assertForbidden();
    $this->delete(route('recurring-entries.manual-costs.destroy', $params))->assertForbidden();
});

test('the database unique constraint also prevents duplicate manual costs without request validation', function () {
    $data = ['date' => '2000-01-01', 'amount' => '10.00', 'currency' => 'EUR'];
    $this->manualEntry->manualCosts()->create($data);
    expect(fn () => $this->manualEntry->manualCosts()->create($data))
        ->toThrow(UniqueConstraintViolationException::class);
});
