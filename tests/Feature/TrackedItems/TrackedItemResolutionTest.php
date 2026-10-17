<?php

use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use App\Services\TrackedItems\TrackedItemResolver;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->actor = User::factory()->create();
    $this->account = createTestAccount($this->actor);
    $this->category = Category::query()->create([
        'user_id' => $this->actor->id, 'name' => 'Spese', 'slug' => 'spese',
        'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true,
    ]);
});

function resolutionReference(User $user, array $attributes = []): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id, 'name' => 'Caffè Roma', 'slug' => 'shared-expense-caffe-roma',
        'is_active' => true, 'settings' => [], ...$attributes,
    ]);
}

function resolutionPayload(object $test, string $endpoint, array $attributes = []): array
{
    return [
        'name' => ' CAFFE   ROMA ', 'account_uuid' => $test->account->uuid,
        'category_uuid' => $test->category->uuid,
        ...($endpoint === 'transactions' ? ['type_key' => 'expense'] : ['direction' => 'expense']),
        ...$attributes,
    ];
}

test('contextual reference creation reuses normalized names independently of slugs', function (string $endpoint) {
    $reference = resolutionReference($this->actor, ['slug' => 'custom-reference']);
    $this->actingAs($this->actor)->postJson(route($endpoint.'.tracked-items.store'), resolutionPayload($this, $endpoint))
        ->assertOk()->assertJsonPath('item.uuid', $reference->uuid);
    expect(TrackedItem::count())->toBe(1)
        ->and($reference->fresh()->compatibleCategories->modelKeys())->toContain($this->category->id);
})->with(['transactions', 'recurring-entries']);

test('UUID association preserves identity hierarchy and previous categories even with ambiguous names', function (string $endpoint) {
    $parent = resolutionReference($this->actor, ['name' => 'Famiglia', 'slug' => 'famiglia']);
    $reference = resolutionReference($this->actor, ['parent_id' => $parent->id]);
    resolutionReference($this->actor, ['slug' => 'duplicate-legacy']);
    $previous = Category::query()->create([
        'user_id' => $this->actor->id, 'name' => 'Precedente', 'slug' => 'precedente',
        'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true,
    ]);
    $reference->compatibleCategories()->attach($previous);
    $this->actingAs($this->actor)->postJson(route($endpoint.'.tracked-items.store'), resolutionPayload($this, $endpoint, ['name' => null, 'tracked_item_uuid' => $reference->uuid]))
        ->assertOk()->assertJsonPath('item.uuid', $reference->uuid);
    expect(TrackedItem::count())->toBe(3)
        ->and($reference->fresh()->parent_id)->toBe($parent->id)
        ->and($reference->fresh()->compatibleCategories->modelKeys())->toContain($previous->id, $this->category->id);
})->with(['transactions', 'recurring-entries']);

test('ambiguous textual requests ask for an existing choice without changing associations', function (string $endpoint) {
    resolutionReference($this->actor);
    resolutionReference($this->actor, ['name' => 'CAFFE ROMA', 'slug' => 'legacy-duplicate']);
    $route = $endpoint === 'shared-expenses' ? 'shared-expenses.counterparties.store' : $endpoint.'.tracked-items.store';
    $payload = $endpoint === 'shared-expenses' ? ['name' => ' Caffé  Roma '] : resolutionPayload($this, $endpoint);
    $this->actingAs($this->actor)->postJson(route($route), $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(TrackedItem::count())->toBe(2)->and(DB::table('tracked_item_categories')->count())->toBe(0);
})->with(['transactions', 'recurring-entries', 'shared-expenses']);

test('shared expense creation and UUID selection reuse the personal catalog', function () {
    $reference = resolutionReference($this->actor);
    $this->actingAs($this->actor)->postJson(route('shared-expenses.counterparties.store'), ['name' => 'CAFFE  ROMA'])
        ->assertCreated()->assertJsonPath('uuid', $reference->uuid);
    $this->postJson(route('shared-expenses.counterparties.store'), ['tracked_item_uuid' => $reference->uuid])
        ->assertCreated()->assertJsonPath('uuid', $reference->uuid);
    expect(TrackedItem::count())->toBe(1)->and($reference->fresh()->is_shared_expense_counterpart)->toBeTrue();
});

test('explicit UUID cannot escape catalog or account authorization', function (string $endpoint) {
    $foreign = User::factory()->create();
    $reference = resolutionReference($foreign);
    $this->actingAs($this->actor)->postJson(route($endpoint.'.tracked-items.store'), resolutionPayload($this, $endpoint, ['tracked_item_uuid' => $reference->uuid]))
        ->assertUnprocessable()->assertJsonValidationErrors('tracked_item_uuid');
    $foreignAccount = createTestAccount($foreign);
    $this->postJson(route($endpoint.'.tracked-items.store'), resolutionPayload($this, $endpoint, ['account_uuid' => $foreignAccount->uuid]))
        ->assertUnprocessable()->assertJsonValidationErrors('account_uuid');
    expect(TrackedItem::count())->toBe(1);
})->with(['transactions', 'recurring-entries']);

test('shared catalogs remain separate and editors resolve to the same account reference', function (string $endpoint) {
    $editor = User::factory()->create();
    AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $this->actor->id, 'joined_at' => now()]);
    $personal = resolutionReference($this->actor);
    $shared = resolutionReference($this->actor, ['account_id' => $this->account->id, 'slug' => 'shared-custom']);
    $this->category->update(['account_id' => $this->account->id]);
    foreach ([$this->actor, $editor] as $actor) {
        $this->actingAs($actor)->postJson(route($endpoint.'.tracked-items.store'), resolutionPayload($this, $endpoint))
            ->assertOk()->assertJsonPath('item.uuid', $shared->uuid);
    }
    $this->postJson(route($endpoint.'.tracked-items.store'), resolutionPayload($this, $endpoint, ['tracked_item_uuid' => $personal->uuid]))
        ->assertUnprocessable()->assertJsonValidationErrors('tracked_item_uuid');
    expect(TrackedItem::count())->toBe(2)->and($personal->fresh()->compatibleCategories)->toHaveCount(0);
})->with(['transactions', 'recurring-entries']);

test('normalized names do not merge punctuation or unrelated catalog identities', function () {
    $reference = resolutionReference($this->actor, ['name' => 'AC ME', 'slug' => 'ac-me']);
    $resolver = app(TrackedItemResolver::class);
    $second = $resolver->resolve($this->actor->id, null, 'AC-ME');
    expect($second->id)->not->toBe($reference->id)->and($second->slug)->toBe('ac-me-2');
    $foreign = User::factory()->create();
    expect($resolver->resolve($foreign->id, null, 'AC ME')->id)->not->toBe($reference->id);
});

test('settings reject normalized duplicate names without changing existing records', function () {
    resolutionReference($this->actor);
    $this->actingAs($this->actor)->postJson(route('tracked-items.store'), ['name' => 'CAFFE  ROMA', 'slug' => 'another-slug', 'is_active' => true, 'is_shared_expense_counterpart' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(TrackedItem::count())->toBe(1);
});

test('personal counterparty choices disambiguate legacy names and exclude other catalogs', function () {
    $first = resolutionReference($this->actor);
    $second = resolutionReference($this->actor, ['name' => 'CAFFE ROMA', 'slug' => 'legacy-duplicate']);
    resolutionReference($this->actor, ['account_id' => $this->account->id]);
    resolutionReference(User::factory()->create());
    resolutionReference($this->actor, ['name' => 'Archiviato', 'slug' => 'inactive', 'is_active' => false]);
    $options = app(TrackedItemResolver::class)->counterpartyOptions($this->actor->id);
    expect(array_column($options, 'value'))->toEqualCanonicalizing([$first->uuid, $second->uuid])
        ->and(array_unique(array_column($options, 'label')))->toHaveCount(2);
});

test('reference normalization handles decomposed accents and unicode whitespace', function (string $name) {
    expect(TrackedItemResolver::normalizeName($name))->toBe('caffe roma');
})->with(['  CAFFE   ROMA ', 'Caffè Roma', "Caffe\u{0300} Roma", "Caffé\u{00a0}Roma"]);

test('textual reuse cannot reactivate a reference below an archived parent', function () {
    $parent = resolutionReference($this->actor, ['name' => 'Parent', 'slug' => 'parent', 'is_active' => false]);
    $reference = resolutionReference($this->actor, ['parent_id' => $parent->id, 'is_active' => false]);
    $this->actingAs($this->actor)->postJson(route('transactions.tracked-items.store'), resolutionPayload($this, 'transactions'))
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(TrackedItem::count())->toBe(2)->and($reference->fresh()->is_active)->toBeFalse();
});

test('transaction search options retain availability for each account instead of exposing a sibling catalog', function () {
    $personal = resolutionReference($this->actor);
    $otherAccount = createTestAccount($this->actor);
    $shared = resolutionReference($this->actor, ['account_id' => $otherAccount->id]);
    $payload = app(MonthlyTransactionSheetService::class)->build($this->actor, 2026, 10);
    $options = collect($payload['editor']['tracked_items']);
    expect($options->firstWhere('value', $personal->uuid)['available_account_uuids'])->toBe([$this->account->uuid])
        ->and($options->firstWhere('value', $shared->uuid)['available_account_uuids'])->toBe([$otherAccount->uuid]);
});

test('materialization reuses normalized account names with custom slugs without changing hierarchy or removing categories', function () {
    $source = resolutionReference($this->actor);
    $this->category->update(['account_id' => $this->account->id]);
    $previous = Category::create([
        'user_id' => $this->actor->id, 'account_id' => $this->account->id, 'name' => 'Precedente', 'slug' => 'precedente',
        'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true,
    ]);
    $parent = resolutionReference($this->actor, ['account_id' => $this->account->id, 'name' => 'Parent', 'slug' => 'parent']);
    $target = resolutionReference($this->actor, ['account_id' => $this->account->id, 'name' => 'CAFFE ROMA', 'slug' => 'account-custom', 'parent_id' => $parent->id, 'settings' => ['transaction_group_keys' => ['bill']]]);
    $target->compatibleCategories()->attach($previous);
    $result = app(SharedAccountTrackedItemCatalogService::class)
        ->materializeSourceTrackedItemForAccount($this->account, $source, [$this->category->id]);
    expect($result->uuid)->toBe($target->uuid)->and($result->parent_id)->toBe($parent->id)
        ->and($result->compatibleCategories->modelKeys())->toContain($previous->id, $this->category->id)
        ->and($result->settings['transaction_group_keys'])->toContain('bill')
        ->and(TrackedItem::count())->toBe(3);
});

test('personal settings cannot move an account reference into the personal catalog', function () {
    $reference = resolutionReference($this->actor, ['account_id' => $this->account->id]);
    $this->actingAs($this->actor)->patchJson(route('tracked-items.update', $reference->uuid), [
        'name' => 'Nome modificato', 'slug' => 'changed', 'is_active' => true,
        'is_shared_expense_counterpart' => false,
    ])->assertNotFound();
    expect($reference->fresh()->account_id)->toBe($this->account->id)->and($reference->fresh()->name)->toBe('Caffè Roma');
});

test('new personal reference options are reusable across personal accounts without exposing shared catalogs', function () {
    $otherPersonal = createTestAccount($this->actor);
    $shared = createTestAccount($this->actor);
    resolutionReference($this->actor, ['account_id' => $shared->id]);
    $response = $this->actingAs($this->actor)->postJson(route('transactions.tracked-items.store'), resolutionPayload($this, 'transactions', ['name' => 'Nuovo riferimento']))
        ->assertOk();
    expect($response->json('item.available_account_uuids'))->toEqualCanonicalizing([$this->account->uuid, $otherPersonal->uuid]);
});

test('materialization follows the stored source UUID after a personal reference is renamed', function () {
    $source = resolutionReference($this->actor, ['name' => 'Nome aggiornato', 'slug' => 'updated-personal']);
    $this->category->update(['account_id' => $this->account->id]);
    $target = resolutionReference($this->actor, ['account_id' => $this->account->id, 'name' => 'Nome precedente', 'slug' => 'old-account', 'settings' => ['source_personal_tracked_item_uuid' => $source->uuid]]);
    $service = app(SharedAccountTrackedItemCatalogService::class);
    expect($service->findExistingTrackedItemForSourceTrackedItem($this->account, $source)->uuid)->toBe($target->uuid)
        ->and($service->materializeSourceTrackedItemForAccount($this->account, $source, [$this->category->id])->uuid)->toBe($target->uuid)
        ->and(TrackedItem::count())->toBe(2);
});
