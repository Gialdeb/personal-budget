<?php

use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('it logs account creation with the authenticated causer', function () {
    $causer = User::factory()->create();
    Activity::query()->delete();

    $this->actingAs($causer);

    $account = createTestAccount($causer, [
        'name' => 'Conto audit',
        'current_balance' => 1250,
    ]);

    $activity = Activity::query()->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('accounts')
        ->and($activity->description)->toBe('account.created')
        ->and($activity->event)->toBe('created')
        ->and($activity->causer_id)->toBe($causer->id)
        ->and($activity->subject_id)->toBe($account->id)
        ->and($activity->subject_type)->toBe(Account::class)
        ->and($activity->properties['attributes']['name'])->toBe('Conto audit')
        ->and($activity->properties['attributes']['current_balance'])->toBe('1250.00');
});

test('it logs only dirty account updates and skips updated at only touches', function () {
    $causer = User::factory()->create();
    $account = createTestAccount($causer, [
        'name' => 'Conto originale',
        'current_balance' => 100,
    ]);
    Activity::query()->delete();

    $this->actingAs($causer);

    $account->touch();

    expect(Activity::query()->count())->toBe(0);

    $account->update([
        'name' => 'Conto aggiornato',
        'current_balance' => 175,
    ]);

    $activity = Activity::query()->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('updated')
        ->and($activity->properties['attributes'])->toHaveKeys(['name', 'current_balance'])
        ->and($activity->properties['old'])->toHaveKeys(['name', 'current_balance'])
        ->and($activity->properties['attributes'])->not->toHaveKey('updated_at')
        ->and($activity->properties['old'])->not->toHaveKey('updated_at')
        ->and($activity->properties['attributes']['name'])->toBe('Conto aggiornato')
        ->and($activity->properties['old']['name'])->toBe('Conto originale');
});

test('it logs transaction deletion with enough data for investigation', function () {
    $causer = User::factory()->create();
    $account = createTestAccount($causer);
    $transaction = userTransaction($causer, $account, [
        'description' => 'Pagamento audit',
        'amount' => '42.50',
    ]);
    Activity::query()->delete();

    $this->actingAs($causer);

    $transaction->delete();

    $activity = Activity::query()->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('transactions')
        ->and($activity->description)->toBe('transaction.deleted')
        ->and($activity->event)->toBe('deleted')
        ->and($activity->causer_id)->toBe($causer->id)
        ->and($activity->subject_id)->toBe($transaction->id)
        ->and($activity->subject_type)->toBe(Transaction::class)
        ->and($activity->properties['old']['description'])->toBe('Pagamento audit')
        ->and($activity->properties['old']['amount'])->toBe('42.50');
});

test('it leaves causer empty for system model changes', function () {
    $user = User::factory()->create();
    Activity::query()->delete();

    createTestAccount($user, [
        'name' => 'Conto sistema',
    ]);

    $activity = Activity::query()->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->causer_type)->toBeNull()
        ->and($activity->event)->toBe('created');
});

test('admin activity log page shows real activities and filters them', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $user = User::factory()->create();
    $account = createTestAccount($user, [
        'name' => 'Conto filtrato',
    ]);
    Activity::query()->delete();

    $this->actingAs($admin);

    $account->update([
        'name' => 'Conto filtrato aggiornato',
    ]);

    userTransaction($user, $account, [
        'description' => 'Transazione fuori filtro',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
    ]);

    $this->get(route('admin.activity-log', [
        'subject_type' => Account::class,
        'event' => 'updated',
        'causer_id' => $admin->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ActivityLog')
            ->where('auth.user.is_admin', true)
            ->where('filters.subject_type', Account::class)
            ->where('filters.event', 'updated')
            ->where('filters.causer_id', $admin->id)
            ->has('activities.data', 1)
            ->where('activities.meta.current_page', 1)
            ->where('activities.meta.last_page', 1)
            ->where('activities.meta.total', 1)
            ->where('activities.links.prev', null)
            ->where('activities.links.next', null)
            ->where('activities.data.0.event', 'updated')
            ->where('activities.data.0.subject.type', Account::class)
            ->where('activities.data.0.subject.label', 'Conto filtrato aggiornato')
            ->where('activities.data.0.causer.id', $admin->id)
            ->where('activities.data.0.changes', fn ($changes): bool => collect($changes)
                ->contains(fn ($change): bool => $change['field'] === 'name'
                    && $change['old'] === 'Conto filtrato'
                    && $change['new'] === 'Conto filtrato aggiornato')));
});
