<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $this->actingAs($this->actor);
    $this->categoryData = ['defer_category' => true, 'inline_account_uuid' => $this->account->uuid, 'name' => 'Corso', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true];
    $this->movement = ['account_uuid' => $this->account->uuid, 'transaction_day' => 17, 'type_key' => 'expense', 'amount' => 20, 'description' => 'Categoria differita'];
    $this->storeUrl = route('transactions.store', ['year' => 2026, 'month' => 10]);
});

function inlineCategory(object $test, array $data = []): string
{
    return $test->postJson(route('categories.store'), [...$test->categoryData, ...$data])->assertOk()->json('category.uuid');
}

test('inline categories remain provisional on cancellation and failed submission', function () {
    $token = inlineCategory($this);
    expect(Category::where('name', 'Corso')->exists())->toBeFalse();
    $this->postJson($this->storeUrl, [...$this->movement, 'category_uuid' => $token, 'amount' => -1])->assertUnprocessable();
    expect(Category::where('name', 'Corso')->exists())->toBeFalse();
    $this->post($this->storeUrl, [...$this->movement, 'category_uuid' => $token])->assertSessionHasNoErrors();
    expect(Transaction::where('description', 'Categoria differita')->sole()->category_id)->toBe(Category::where('name', 'Corso')->sole()->id);
});

test('nested provisional parents and references are saved together with the main form', function () {
    $parent = inlineCategory($this, ['name' => 'Formazione', 'is_selectable' => false]);
    $child = inlineCategory($this, ['name' => 'Inglese', 'parent_uuid' => $parent]);
    $reference = $this->postJson(route('transactions.tracked-items.store'), ['draft' => true, 'name' => 'Scuola', 'account_uuid' => $this->account->uuid, 'category_uuid' => $child, 'type_key' => 'expense'])->assertOk()->json('item.value');
    expect(Category::whereIn('name', ['Formazione', 'Inglese'])->count())->toBe(0);
    $this->post($this->storeUrl, [...$this->movement, 'category_uuid' => $child, 'tracked_item_uuid' => $reference])->assertSessionHasNoErrors();
    $category = Category::where('name', 'Inglese')->sole();
    expect($category->parent->name)->toBe('Formazione')->and(Transaction::where('description', 'Categoria differita')->sole()->trackedItem->name)->toBe('Scuola');
});

test('budget transfers require confirmation and commit atomically with the movement', function () {
    $parent = Category::create(['user_id' => $this->actor->id, 'name' => 'Casa', 'slug' => 'house', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => false]);
    $budget = Budget::create(['user_id' => $this->actor->id, 'category_id' => $parent->id, 'year' => 2026, 'month' => 10, 'amount' => 100, 'budget_type' => 'limit']);
    $token = inlineCategory($this, ['parent_uuid' => $parent->uuid]);
    $effects = $this->postJson(route('categories.inline-effects'), ['account_uuid' => $this->account->uuid, 'category_uuid' => $token])->assertOk()->assertJsonCount(1, 'transfers')->json();
    $this->postJson($this->storeUrl, [...$this->movement, 'category_uuid' => $token])->assertUnprocessable();
    expect($budget->fresh()->category_id)->toBe($parent->id)->and($parent->children()->count())->toBe(0);
    $this->postJson($this->storeUrl, [...$this->movement, 'category_uuid' => $token, 'amount' => -1, 'inline_budget_confirmation' => $effects['confirmation']])->assertUnprocessable();
    expect($budget->fresh()->category_id)->toBe($parent->id)->and($parent->children()->count())->toBe(0);
    $this->post($this->storeUrl, [...$this->movement, 'category_uuid' => $token, 'inline_budget_confirmation' => $effects['confirmation']])->assertSessionHasNoErrors();
    expect($budget->fresh()->category_id)->toBe(Category::where('name', 'Corso')->sole()->id)->and($budget->fresh()->amount)->toBe('100.00');
});

test('budgets added after preparation block the whole main submission', function () {
    $parent = Category::create(['user_id' => $this->actor->id, 'name' => 'Casa', 'slug' => 'house', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => false]);
    $token = inlineCategory($this, ['parent_uuid' => $parent->uuid]);
    $budget = Budget::create(['user_id' => $this->actor->id, 'category_id' => $parent->id, 'year' => 2026, 'month' => 10, 'amount' => 100, 'budget_type' => 'limit']);
    $this->postJson($this->storeUrl, [...$this->movement, 'category_uuid' => $token])->assertUnprocessable();
    expect($budget->fresh()->category_id)->toBe($parent->id)->and($parent->children()->count())->toBe(0)->and(Transaction::count())->toBe(0);
});

test('a repeated submit never duplicates its provisional categories', function () {
    $token = inlineCategory($this);
    $payload = [...$this->movement, 'category_uuid' => $token];
    $this->post($this->storeUrl, $payload)->assertSessionHasNoErrors();
    $this->postJson($this->storeUrl, $payload)->assertUnprocessable();
    expect(Category::where('name', 'Corso')->count())->toBe(1)->and(Transaction::where('description', 'Categoria differita')->count())->toBe(1);
});

test('provisional categories cannot cross users or accounts', function () {
    $token = inlineCategory($this);
    $otherAccount = createTestAccount($this->actor);
    $this->postJson($this->storeUrl, [...$this->movement, 'account_uuid' => $otherAccount->uuid, 'category_uuid' => $token])->assertUnprocessable();
    $this->actingAs(User::factory()->create())->postJson($this->storeUrl, [...$this->movement, 'category_uuid' => $token])->assertUnprocessable();
    expect(Category::where('name', 'Corso')->exists())->toBeFalse();
});

test('changed budget amounts invalidate the confirmation without partial writes', function () {
    $parent = Category::create(['user_id' => $this->actor->id, 'name' => 'Casa', 'slug' => 'house', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => false]);
    $budget = Budget::create(['user_id' => $this->actor->id, 'category_id' => $parent->id, 'year' => 2026, 'month' => 10, 'amount' => 100, 'budget_type' => 'limit']);
    $token = inlineCategory($this, ['parent_uuid' => $parent->uuid]);
    $confirmation = $this->postJson(route('categories.inline-effects'), ['account_uuid' => $this->account->uuid, 'category_uuid' => $token])->assertOk()->json('confirmation');
    $budget->update(['amount' => 125]);
    $this->postJson($this->storeUrl, [...$this->movement, 'category_uuid' => $token, 'inline_budget_confirmation' => $confirmation])->assertUnprocessable()->assertJsonValidationErrors('category_uuid');
    expect($budget->fresh()->category_id)->toBe($parent->id)->and($budget->fresh()->amount)->toBe('125.00')->and($parent->children()->count())->toBe(0)->and(Transaction::count())->toBe(0);
});

test('nested provisional categories preview the final budget destination', function () {
    $parent = Category::create(['user_id' => $this->actor->id, 'name' => 'Formazione', 'slug' => 'formazione', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => false]);
    $budget = Budget::create(['user_id' => $this->actor->id, 'category_id' => $parent->id, 'year' => 2026, 'month' => 10, 'amount' => 100, 'budget_type' => 'limit']);
    $middle = inlineCategory($this, ['name' => 'Lingue', 'parent_uuid' => $parent->uuid, 'is_selectable' => false]);
    $leaf = inlineCategory($this, ['name' => 'Inglese', 'parent_uuid' => $middle]);
    $effects = $this->postJson(route('categories.inline-effects'), ['account_uuid' => $this->account->uuid, 'category_uuid' => $leaf])->assertOk()->assertJsonPath('transfers.0.source', 'Formazione')->assertJsonPath('transfers.0.destination', 'Inglese')->json();
    $this->post($this->storeUrl, [...$this->movement, 'category_uuid' => $leaf, 'inline_budget_confirmation' => $effects['confirmation']])->assertSessionHasNoErrors();
    expect($budget->fresh()->category_id)->toBe(Category::where('name', 'Inglese')->sole()->id)->and($budget->fresh()->amount)->toBe('100.00');
});
