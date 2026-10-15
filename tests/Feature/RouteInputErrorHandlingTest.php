<?php

use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\User;
use App\Services\Counterparties\CounterpartyViewService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config(['app.debug' => false, 'counterparty_ledger.public_enabled' => true, 'features.credits_debts.enabled' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('user');
    $this->actingAs($this->user);
});

test('missing counterparties render the existing safe 404 page', function () {
    $this->get('/counterparties/'.Str::uuid().'?status=all&type=all')->assertNotFound()
        ->assertSee('Soamco Budget')->assertDontSee('SQLSTATE')->assertDontSee('Stack trace');
});

test('malformed counterparties never query the uuid column', function (string $value) {
    $queries = [];
    DB::listen(function ($event) use (&$queries): void {
        $queries[] = $event->sql;
    });
    $this->get('/counterparties/'.$value)->assertNotFound();
    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'counterparty_relations')))->toBeFalse();
})->with(['invalid', 'b6847ac2-58b8-402b-adee-be348756615d&status=all&type=all']);

test('another users counterparty is hidden', function () {
    $relation = CounterpartyRelation::factory()->create();
    $this->get(route('counterparties.show', $relation))->assertNotFound();
});

test('counterparty filters safely ignore unknown and structured values', function (array $query) {
    $relation = CounterpartyRelation::factory()->create(['user_id' => $this->user->id, 'activated_at' => now()]);
    $this->get(route('counterparties.show', $relation).'?'.http_build_query($query))->assertOk();
})->with([
    [['status' => 'all', 'type' => 'all']],
    [['status' => 'unknown', 'type' => 'unknown', 'year' => 'invalid', 'month' => 'invalid']],
    [['status' => ['bad'], 'type' => ['bad'], 'scope' => ['shared']]],
]);

test('deleted counterparties return 404', function () {
    $relation = CounterpartyRelation::factory()->create(['user_id' => $this->user->id]);
    $url = route('counterparties.show', $relation);
    $relation->delete();
    $this->get($url)->assertNotFound();
});

test('every explicit uuid route rejects malformed parameters before binding', function () {
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/')) {
            foreach ($route->bindingFields() as $parameter => $field) {
                if ($field === 'uuid') {
                    expect($route->wheres[$parameter] ?? null, $route->uri())->not->toBeNull();
                    expect(preg_match('~^('.$route->wheres[$parameter].')$~D', 'bad&status=all'))->toBe(0);
                    $checked++;
                }
            }
        }
    }
    expect($checked)->toBeGreaterThan(50);
});

test('financial filters reject invalid uuid dates and structured strings', function (string $path, array $query) {
    $this->getJson($path.'?'.http_build_query($query))->assertUnprocessable();
})->with([
    ['/shared-expenses', ['entry' => 'invalid']],
    ['/shared-expenses', ['counterparty' => 'invalid']],
    ['/shared-expenses', ['date_from' => 'invalid']],
    ['/shared-expenses', ['year' => 999999999]],
    ['/credits-debts', ['account_uuid' => 'invalid']],
    ['/credits-debts', ['type' => ['bad']]],
    ['/credits-debts', ['status' => ['bad']]],
    ['/credits-debts', ['month' => ['bad']]],
    ['/credits-debts', ['year' => 'unknown']],
    ['/credits-debts', ['month' => 'unknown']],
]);

test('uuid mutation endpoints return 404 for missing and malformed resources', function (string $method, string $path, string $value) {
    $this->json($method, str_replace('{uuid}', $value, $path))->assertNotFound();
})->with([
    ['DELETE', '/credits-debts/{uuid}'],
    ['DELETE', '/transactions/2026/10/{uuid}'],
    ['PATCH', '/transactions/2026/10/{uuid}/restore'],
    ['DELETE', '/transactions/2026/10/{uuid}/force'],
    ['DELETE', '/shared-expenses/ledger/{uuid}'],
    ['DELETE', '/shared-expenses/settlements/{uuid}'],
    ['DELETE', '/recurring-entries/{uuid}'],
    ['DELETE', '/settings/accounts/{uuid}'],
    ['GET', '/account-invitations/{uuid}/onboarding'],
])->with(['invalid', 'b6847ac2-58b8-402b-adee-be348756615d']);

test('transaction uuid query filters are validated', function (string $key) {
    $this->getJson('/transactions/2026/10?'.$key.'=invalid')->assertUnprocessable();
})->with(['highlight', 'template']);

test('invalid route periods return 404', function (string $period) {
    $this->getJson('/transactions/'.$period)->assertNotFound();
})->with(['999999999/10', '2026/99', '0/1']);

test('a missing counterparty relation target is handled as 404', function () {
    $relation = CounterpartyRelation::factory()->create(['user_id' => $this->user->id]);
    $relation->setRelation('counterparty', null);
    try {
        app(CounterpartyViewService::class)->show($this->user, $relation);
        $this->fail('Expected missing resource');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
});

test('foreign accounts and transactions are hidden while credit policy retains 403', function () {
    $other = User::factory()->create();
    $account = createTestAccount($other);
    $transaction = userTransaction($other, $account);
    $credit = CreditDebtItem::factory()->create(['user_id' => $other->id]);

    $this->deleteJson(route('accounts.destroy', $account))->assertNotFound();
    $this->deleteJson(route('transactions.destroy', ['year' => $transaction->transaction_date->year, 'month' => $transaction->transaction_date->month, 'transaction' => $transaction]))->assertNotFound();
    $this->getJson(route('credits-debts.show', $credit))->assertForbidden();
});

test('soft deleted transactions and credits return 404', function () {
    $account = createTestAccount($this->user);
    $transaction = userTransaction($this->user, $account);
    $credit = CreditDebtItem::factory()->create(['user_id' => $this->user->id]);
    $transaction->delete();
    $credit->delete();

    $this->deleteJson(route('transactions.destroy', ['year' => $transaction->transaction_date->year, 'month' => $transaction->transaction_date->month, 'transaction' => $transaction]))->assertNotFound();
    $this->getJson(route('credits-debts.show', $credit))->assertNotFound();
});

test('unknown scalar credit filters are harmless and valid filters keep working', function (string $query) {
    $this->getJson('/credits-debts?'.$query)->assertOk();
})->with(['status=unknown&type=unknown', 'status=all&type=all&year=2026&month=10']);
