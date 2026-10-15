<?php

use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 15));
    $this->historyUser = User::factory()->create(['locale' => 'it']);
    $account = createTestAccount($this->historyUser);
    $this->historyEntry = RecurringEntry::query()->create([
        'user_id' => $this->historyUser->id, 'account_id' => $account->id,
        'title' => 'Piano infinito', 'direction' => 'expense', 'currency' => 'EUR',
        'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true,
        'start_date' => '2024-01-01', 'recurrence_type' => 'monthly',
        'recurrence_interval' => 1, 'end_mode' => 'never', 'expected_amount' => 120,
        'auto_create_transaction' => false,
    ]);
    for ($i = 0; $i < 72; $i++) {
        $date = sprintf('%d-%02d-%02d', 2024 + intdiv($i, 24), intdiv($i % 24, 2) + 1, $i % 2 ? 15 : 1);
        $row = $this->historyEntry->occurrences()->create(['sequence_number' => $i + 1, 'expected_date' => $date, 'due_date' => $date, 'expected_amount' => 120, 'status' => 'pending']);
        if ($i < 52) {
            $transaction = Transaction::query()->create(['user_id' => $this->historyUser->id, 'account_id' => $account->id, 'direction' => 'expense', 'amount' => $i % 3 ? 120 : 80, 'currency' => 'EUR', 'transaction_date' => $date, 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
            $row->paymentAllocations()->create(['transaction_id' => $transaction->id, 'amount_cents' => $i % 3 ? 12000 : 8000]);
        }
    }
    foreach ([[120, 135], [135, 110], [110, 125], [125, 120]] as $i => [$previous, $new]) {
        $this->historyEntry->amountChanges()->create(['previous_amount' => $previous, 'new_amount' => $new, 'currency' => 'EUR', 'effective_from' => '2026-0'.($i + 1).'-01']);
    }
    $this->actingAs($this->historyUser);
});

test('measures recurring history response', function () {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->getJson(route('recurring-entries.show', $this->historyEntry->uuid))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $metrics = ['records' => count($response->json('recurringEntry.occurrences')), 'bytes' => strlen($response->getContent()), 'queries' => $queries];
    fwrite(STDOUT, json_encode($metrics).PHP_EOL);
    expect($metrics['records'])->toBe(12)->and($metrics['queries'])->toBeLessThan(70)->and($metrics['bytes'])->toBeLessThan(20000);
    $response->assertJsonPath('recurringEntry.occurrence_page.year', 2026)
        ->assertJsonPath('recurringEntry.occurrence_page.next_offset', 12)
        ->assertJsonPath('recurringEntry.occurrence_years.0.count', 24)
        ->assertJsonPath('recurringEntry.summary.total_occurrences', 72)
        ->assertJsonPath('recurringEntry.summary.paid_amount', 400)
        ->assertJsonPath('recurringEntry.summary.paid_count', 2);
    if ($directory = getenv('RECURRING_HISTORY_FIXTURE')) {
        file_put_contents($directory.'/initial.json', $response->getContent());
        $response = $this->get(route('recurring-entries.show', $this->historyEntry->uuid))->assertOk();
        file_put_contents($directory.'/initial.html', $response->getContent());
        file_put_contents($directory.'/entry.txt', $this->historyEntry->uuid);
        $unlinked = Transaction::query()->create(['user_id' => $this->historyUser->id, 'account_id' => $this->historyEntry->account_id, 'direction' => 'expense', 'amount' => 40, 'currency' => 'EUR', 'transaction_date' => '2026-10-15', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'description' => 'Pagamento già registrato']);
        $target = $this->historyEntry->occurrences()->where('sequence_number', 49)->firstOrFail();
        $candidates = $this->getJson(route('recurring-entries.occurrences.payment-candidates', [$this->historyEntry->uuid, $target->uuid]))->assertOk();
        file_put_contents($directory.'/candidates.json', $candidates->getContent());

        foreach ([2024, 2025, 2026] as $year) {
            foreach ([0, 12] as $offset) {
                $chunk = $this->getJson(route('recurring-entries.show', [$this->historyEntry->uuid, 'occurrence_year' => $year, 'occurrence_offset' => $offset]))->assertOk();
                file_put_contents($directory.'/'.$year.'-'.$offset.'.json', $chunk->getContent());
            }
        }
        $old = $this->historyEntry->occurrences()->where('sequence_number', 20)->firstOrFail();
        $deep = $this->get(route('recurring-entries.show', [$this->historyEntry->uuid, 'highlight' => $old->uuid]))->assertOk();
        file_put_contents($directory.'/deep.html', $deep->getContent());
        file_put_contents($directory.'/highlight.txt', $old->uuid);
    }
});

test('loads bounded year chunks and locates an old occurrence without loading its preceding history', function () {
    $url = route('recurring-entries.show', $this->historyEntry->uuid);
    $first = $this->getJson($url.'?occurrence_year=2025')->assertOk()->assertJsonCount(12, 'occurrences')->assertJsonPath('next_offset', 12);
    $second = $this->getJson($url.'?occurrence_year=2025&occurrence_offset=12')->assertOk()->assertJsonCount(12, 'occurrences')->assertJsonPath('next_offset', null);
    expect(array_intersect(array_column($first->json('occurrences'), 'uuid'), array_column($second->json('occurrences'), 'uuid')))->toBe([]);
    $target = $this->historyEntry->occurrences()->where('sequence_number', 20)->firstOrFail();
    $deep = $this->getJson($url.'?highlight='.$target->uuid)->assertOk()->assertJsonPath('recurringEntry.occurrence_page.year', 2024)->assertJsonPath('recurringEntry.occurrence_page.offset', 12)->assertJsonCount(12, 'recurringEntry.occurrences');
    expect(array_column($deep->json('recurringEntry.occurrences'), 'uuid'))->toContain($target->uuid);
    $this->getJson($url.'?occurrence_year=2027')->assertOk()->assertJsonCount(0, 'occurrences')->assertJsonPath('next_offset', null);
    $this->getJson($url.'?occurrence_year=2025&occurrence_offset=-1')->assertUnprocessable();
    $this->actingAs(User::factory()->create())->getJson($url.'?occurrence_year=2025')->assertNotFound();
});

test('year grouping uses the overridden due date', function () {
    $row = $this->historyEntry->occurrences()->where('sequence_number', 24)->firstOrFail();
    $row->update(['due_date' => '2025-01-01']);
    $response = $this->getJson(route('recurring-entries.show', $this->historyEntry->uuid))->assertOk();
    expect(collect($response->json('recurringEntry.occurrence_years'))->keyBy('year')->get(2024)['count'])->toBe(23);
    $chunk = $this->getJson(route('recurring-entries.show', [$this->historyEntry->uuid, 'occurrence_year' => 2025]))->assertOk();
    expect(array_column($chunk->json('occurrences'), 'uuid'))->toContain($row->uuid);
});

test('keeps global summaries exact across batches without per occurrence queries', function () {
    for ($i = 0; $i < 150; $i++) {
        $date = now()->setDate(2027, 1, 1)->addDays($i)->toDateString();
        $this->historyEntry->occurrences()->create(['sequence_number' => 73 + $i, 'expected_date' => $date, 'expected_amount' => 120, 'status' => 'pending']);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->getJson(route('recurring-entries.show', $this->historyEntry->uuid))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $response->assertJsonCount(12, 'recurringEntry.occurrences')
        ->assertJsonPath('recurringEntry.summary.total_occurrences', 222)
        ->assertJsonPath('recurringEntry.summary.paid_amount', 400)
        ->assertJsonPath('recurringEntry.summary.settled_occurrences', 34)
        ->assertJsonPath('recurringEntry.summary.remaining_amount', 21120);
    expect($queries)->toBeLessThan(90);
});

test('shows years chronologically and only flags overdue unpaid residuals', function () {
    $this->historyEntry->occurrences()->create(['sequence_number' => 73, 'expected_date' => '2027-01-01', 'expected_amount' => 120, 'status' => 'pending']);
    $this->historyEntry->occurrences()->create(['sequence_number' => 74, 'expected_date' => '2023-01-01', 'expected_amount' => 120, 'status' => 'skipped']);
    $this->historyEntry->occurrences()->create(['sequence_number' => 75, 'expected_date' => '2023-02-01', 'expected_amount' => 120, 'status' => 'cancelled']);
    $response = $this->getJson(route('recurring-entries.show', $this->historyEntry->uuid))->assertOk();
    $years = collect($response->json('recurringEntry.occurrence_years'))->keyBy('year');
    expect($years->keys()->all())->toBe([2023, 2024, 2025, 2026, 2027]);
    expect($years[2024]['overdue_count'])->toBe(8)->and($years[2024]['overdue_amount'])->toBe(320);
    expect($years[2023]['overdue_count'])->toBe(0)->and($years[2027]['overdue_count'])->toBe(0);
    expect($response->json('recurringEntry.occurrence_page.year'))->toBe(2026);
    $partial = $this->historyEntry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $automatic = Transaction::query()->create(['user_id' => $this->historyUser->id, 'account_id' => $this->historyEntry->account_id, 'recurring_entry_occurrence_id' => $partial->id, 'direction' => 'expense', 'amount' => 40, 'currency' => 'EUR', 'transaction_date' => '2024-01-02', 'kind' => 'scheduled', 'source_type' => 'manual', 'status' => 'confirmed']);
    $partial->update(['converted_transaction_id' => $automatic->id, 'status' => 'completed']);
    $updated = $this->getJson(route('recurring-entries.show', $this->historyEntry->uuid))->assertOk();
    $years = collect($updated->json('recurringEntry.occurrence_years'))->keyBy('year');
    expect($years[2024]['overdue_count'])->toBe(7)->and($years[2024]['overdue_amount'])->toBe(280);

});
