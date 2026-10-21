<?php

use App\Models\Category;
use App\Models\CompoundRecurringEntry;
use App\Models\RecurringPlanRefund;
use App\Models\RecurringPlanRefundPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\DeleteRecurringEntryService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\RecurringPlanRefundService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 21)->setTime(12, 0));
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $this->creditAccount = createTestAccount($this->actor, ['opening_balance' => 500, 'current_balance' => 500]);
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Subscription', 'slug' => 'subscription', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->entry = app(RecurringEntryManagementService::class)->store($this->actor, [
        'title' => 'Subscription', 'account_id' => $this->account->id, 'category_id' => $category->id,
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active',
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 16],
        'start_date' => '2026-10-16', 'end_mode' => 'never', 'expected_amount' => 100,
        'auto_generate_occurrences' => true, 'auto_create_transaction' => false, 'is_active' => true,
    ]);
    $this->occurrence = $this->entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $this->payment = app(RecurringEntryPostingService::class)->post($this->occurrence);
    $this->refundData = [
        'request_uuid' => (string) Str::uuid(), 'account_uuid' => $this->creditAccount->uuid,
        'transaction_date' => '2026-10-21', 'expected_total_cents' => 10000, 'irreversible' => true,
    ];
    $this->actingAs($this->actor);
});

test('plan refund creates one credit and keeps the original payment plan and occurrences unchanged', function () {
    $entryBefore = $this->entry->fresh()->getAttributes();
    $occurrencesBefore = $this->entry->occurrences()->get()->map->getAttributes()->all();
    $paymentBefore = $this->payment->fresh()->getAttributes();
    $balanceBefore = $this->account->fresh()->current_balance;

    $this->post(route('recurring-entries.plan-refund.store', $this->entry->uuid), $this->refundData)->assertSessionHasNoErrors()->assertRedirect();

    $refund = RecurringPlanRefund::sole();
    expect($refund->amount_cents)->toBe(10000)
        ->and($refund->payments()->sole()->transaction_id)->toBe($this->payment->id)
        ->and($refund->transaction->direction->value)->toBe('income')
        ->and($refund->transaction->account_id)->toBe($this->creditAccount->id)
        ->and((float) $refund->transaction->amount)->toBe(100.0)
        ->and($this->payment->fresh()->getAttributes())->toBe($paymentBefore)
        ->and($this->entry->fresh()->getAttributes())->toBe($entryBefore)
        ->and($this->entry->occurrences()->get()->map->getAttributes()->all())->toBe($occurrencesBefore)
        ->and($this->account->fresh()->current_balance)->toBe($balanceBefore)
        ->and((float) $this->creditAccount->fresh()->current_balance)->toBe(600.0);
    $this->getJson(route('recurring-entries.plan-refund.preview', $this->entry->uuid))->assertOk()
        ->assertJsonPath('total_cents', 0)->assertJsonPath('refunds.0.payments.0.uuid', $this->payment->uuid);
});

test('retrying the same request returns the same credit and a new request cannot refund it twice', function () {
    $service = app(RecurringPlanRefundService::class);
    $first = $service->refund($this->actor, $this->entry->uuid, $this->refundData);
    $second = $service->refund($this->actor, $this->entry->uuid, $this->refundData);
    expect($second->id)->toBe($first->id)->and(RecurringPlanRefund::count())->toBe(1);
    expect(fn () => $service->refund($this->actor, $this->entry->uuid, [...$this->refundData, 'request_uuid' => (string) Str::uuid()]))->toThrow(ValidationException::class);
    expect(Transaction::where('kind', 'refund')->count())->toBe(1);
});

test('refund includes only the real allocated portion and preserves partial payments', function () {
    $next = $this->entry->occurrences()->where('sequence_number', 2)->firstOrFail();
    $next->update(['due_date' => '2026-10-20']);
    $transaction = Transaction::create([
        'user_id' => $this->actor->id, 'account_id' => $this->account->id, 'direction' => 'expense',
        'amount' => 60, 'currency' => 'EUR', 'transaction_date' => '2026-10-20', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
    ]);
    $allocation = app(AllocateOccurrencePaymentService::class)->allocate($next, $transaction, $this->actor, '40');
    $remaining = $next->fresh()->remainingPaymentCents();
    expect(app(RecurringPlanRefundService::class)->preview($this->actor, $this->entry->uuid)['total_cents'])->toBe(14000);
    app(RecurringPlanRefundService::class)->refund($this->actor, $this->entry->uuid, [...$this->refundData, 'expected_total_cents' => 14000]);
    expect(RecurringPlanRefundPayment::where('transaction_id', $transaction->id)->sole()->amount_cents)->toBe(4000)
        ->and($next->fresh()->remainingPaymentCents())->toBe($remaining)
        ->and($allocation->fresh()->amount_cents)->toBe(4000);
    expect(fn () => $allocation->delete())->toThrow(ValidationException::class);
    $nextPayment = $transaction->replicate(['uuid']);
    $nextPayment->save();
    app(AllocateOccurrencePaymentService::class)->allocate($next->fresh(), $nextPayment, $this->actor, '60');
    expect($next->fresh()->remainingPaymentCents())->toBe(0)
        ->and(app(RecurringPlanRefundService::class)->preview($this->actor, $this->entry->uuid)['total_cents'])->toBe(6000);
});

test('new credits and original payments cannot be edited deleted or refunded through ordinary flows', function () {
    $refund = app(RecurringPlanRefundService::class)->refund($this->actor, $this->entry->uuid, $this->refundData);
    foreach ([$this->payment->fresh(), $refund] as $transaction) {
        expect(fn () => $transaction->update(['amount' => 1]))->toThrow(ValidationException::class);
        $transaction->refresh();
        expect(fn () => $transaction->forceDelete())->toThrow(ValidationException::class);
        expect(fn () => app(TransactionRefundService::class)->refund($transaction))->toThrow(ValidationException::class);
    }
    expect(fn () => app(TransactionRefundService::class)->undo($refund))->toThrow(ValidationException::class);
    expect(fn () => app(UndoRecurringOccurrenceConversionService::class)->undo($this->occurrence->fresh()))->toThrow(ValidationException::class);
    expect(Transaction::where('kind', 'refund')->count())->toBe(1);
});

test('invalid totals currency permissions and confirmation do not create partial refunds', function () {
    $foreign = createTestAccount($this->actor, ['currency' => 'USD', 'currency_code' => 'USD']);
    $url = route('recurring-entries.plan-refund.store', $this->entry->uuid);
    $this->post($url, [...$this->refundData, 'expected_total_cents' => 1])->assertSessionHasErrors('refund');
    $this->post($url, [...$this->refundData, 'account_uuid' => $foreign->uuid])->assertSessionHasErrors('account_uuid');
    $this->post($url, [...$this->refundData, 'irreversible' => false])->assertSessionHasErrors('irreversible');
    $this->post($url, [...$this->refundData, 'transaction_date' => '2026-10-22'])->assertSessionHasErrors('transaction_date');
    $this->actingAs(User::factory()->create())->post($url, $this->refundData)->assertForbidden();
    expect(RecurringPlanRefund::count())->toBe(0)->and(Transaction::where('kind', 'refund')->count())->toBe(0);
});

test('failure after inserting the refund rolls back credit links and balance changes', function () {
    $mutations = Mockery::mock(app(TransactionMutationService::class))->makePartial();
    $mutations->shouldReceive('recalculateAccount')->once()->andThrow(new RuntimeException('Recalculation failed'));
    $this->app->instance(TransactionMutationService::class, $mutations);
    expect(fn () => app(RecurringPlanRefundService::class)->refund($this->actor, $this->entry->uuid, $this->refundData))->toThrow(RuntimeException::class);
    expect(RecurringPlanRefund::count())->toBe(0)
        ->and(RecurringPlanRefundPayment::count())->toBe(0)
        ->and(Transaction::where('kind', 'refund')->count())->toBe(0)
        ->and((float) $this->creditAccount->fresh()->current_balance)->toBe(500.0)
        ->and($this->occurrence->fresh()->status->value)->toBe('completed');
});

test('ordinary refunds are excluded and unrecorded scheduled amounts are never credited', function () {
    app(TransactionRefundService::class)->refund($this->payment);
    expect(app(RecurringPlanRefundService::class)->preview($this->actor, $this->entry->uuid)['total_cents'])->toBe(0);
    $this->post(route('recurring-entries.plan-refund.store', $this->entry->uuid), $this->refundData)->assertSessionHasErrors('refund');
    expect(RecurringPlanRefund::count())->toBe(0);
});

test('crediting the original account preserves settlement and subsequent lifecycle runs', function () {
    $entryBefore = $this->entry->fresh()->getAttributes();
    app(RecurringPlanRefundService::class)->refund($this->actor, $this->entry->uuid, [...$this->refundData, 'account_uuid' => $this->account->uuid]);
    expect($this->occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($this->occurrence->fresh()->status->value)->toBe('completed')
        ->and($this->entry->fresh()->getAttributes())->toBe($entryBefore)
        ->and((float) $this->account->fresh()->current_balance)->toBe(1000.0);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::where('kind', 'refund')->count())->toBe(1)
        ->and($this->occurrence->fresh()->status->value)->toBe('completed');
});

test('compound refund includes paid archived children without changing their states', function () {
    $parent = CompoundRecurringEntry::factory()->create([
        'user_id' => $this->actor->id, 'account_id' => $this->account->id, 'category_id' => $this->entry->category_id, 'name' => 'IMU',
    ]);
    $component = $parent->components()->create(['name' => 'Acconto', 'recurring_entry_id' => $this->entry->id]);
    $component->forceFill(['archived_at' => now()])->save();
    $before = $this->entry->fresh()->getAttributes();
    $this->getJson(route('recurring-entries.plan-refund.preview', $parent->uuid))->assertOk()->assertJsonPath('total_cents', 10000);
    $this->post(route('recurring-entries.plan-refund.store', $parent->uuid), $this->refundData)->assertSessionHasNoErrors();
    expect(RecurringPlanRefund::sole()->plan_uuid)->toBe($parent->uuid)
        ->and($this->entry->fresh()->getAttributes())->toBe($before)
        ->and($component->fresh()->archived_at)->not->toBeNull();
});

test('one transaction allocated to multiple occurrences is refunded only for its actual allocated total', function () {
    $rows = $this->entry->occurrences()->whereIn('sequence_number', [2, 3])->orderBy('sequence_number')->get();
    $transaction = Transaction::create([
        'user_id' => $this->actor->id, 'account_id' => $this->account->id, 'direction' => 'expense',
        'amount' => 60, 'currency' => 'EUR', 'transaction_date' => '2026-10-20', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
    ]);
    foreach ($rows as $index => $row) {
        $row->update(['due_date' => '2026-10-20']);
        app(AllocateOccurrencePaymentService::class)->allocate($row, $transaction, $this->actor, $index === 0 ? '40' : '20');
    }
    app(RecurringPlanRefundService::class)->refund($this->actor, $this->entry->uuid, [...$this->refundData, 'expected_total_cents' => 16000]);
    expect(RecurringPlanRefund::sole()->amount_cents)->toBe(16000)
        ->and(RecurringPlanRefundPayment::where('transaction_id', $transaction->id)->count())->toBe(2)
        ->and((int) RecurringPlanRefundPayment::where('transaction_id', $transaction->id)->sum('amount_cents'))->toBe(6000)
        ->and(Transaction::where('kind', 'refund')->count())->toBe(1);
});

test('irreversible refunds prevent plan deletion without removing its history', function () {
    app(RecurringPlanRefundService::class)->refund($this->actor, $this->entry->uuid, $this->refundData);
    expect(fn () => app(DeleteRecurringEntryService::class)->delete($this->entry))->toThrow(ValidationException::class);
    expect($this->entry->fresh())->not->toBeNull()
        ->and($this->payment->fresh())->not->toBeNull()
        ->and(RecurringPlanRefund::count())->toBe(1);
});
