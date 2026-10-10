<?php

use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Models\WebsiteIdentity;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

function quickTemplateFixture(): array
{
    $user = User::factory()->create();
    UserYear::create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
    UserSetting::updateOrCreate(['user_id' => $user->id], ['active_year' => 2026]);
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Carburante', 'slug' => 'carburante', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $reference = TrackedItem::create(['user_id' => $user->id, 'name' => 'Q8', 'slug' => 'q8', 'is_active' => true]);
    $data = ['name' => 'Benzina', 'icon' => 'Fuel', 'color' => 'primary', 'direction' => 'expense', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'tracked_item_uuid' => $reference->uuid, 'description' => 'Rifornimento', 'default_amount' => null, 'shared_enabled' => false, 'shared_counterparty_uuid' => null, 'shared_payer' => 'self', 'shared_split_mode' => 'equal', 'my_share_basis_points' => 5000, 'is_active' => true];

    return [$user, $account, $category, $reference, $data];
}

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('quick actions retain ordinary references but reject them as shared counterparties', function () {
    [$user, , , $amazon, $data] = quickTemplateFixture();
    $amazon->update(['name' => 'Amazon', 'type' => 'shared_expense_counterparty', 'is_shared_expense_counterpart' => false]);
    $mario = TrackedItem::create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($user)->get(route('transaction-templates.index'))->assertInertia(fn (Assert $page) => $page
        ->has('references', 2)->where('references.0.uuid', $amazon->uuid)
        ->where('references.0.is_shared_expense_counterpart', false)
        ->where('references.1.uuid', $mario->uuid)->where('references.1.is_shared_expense_counterpart', true));
    $this->post(route('transaction-templates.store'), [...$data, 'shared_enabled' => true, 'shared_counterparty_uuid' => $amazon->uuid])
        ->assertSessionHasErrors('shared_counterparty_uuid');
    $this->post(route('transaction-templates.store'), [...$data, 'shared_enabled' => true, 'shared_counterparty_uuid' => $mario->uuid])
        ->assertSessionHasNoErrors();
    expect(TransactionTemplate::sole()->shared_counterparty_id)->toBe($mario->id);
});

test('active quick actions expose only owned active templates with category and reference appearance', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $category->update(['icon' => 'beef', 'color' => '#047857']);
    $identity = WebsiteIdentity::factory()->create(['status' => 'ready', 'logo_path' => 'logos/q8.png']);
    $reference->update(['website_identity_id' => $identity->id]);
    $this->actingAs($user)->post(route('transaction-templates.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('transaction-templates.store'), [...$data, 'name' => 'Seconda azione'])->assertSessionHasNoErrors();
    $this->post(route('transaction-templates.store'), [...$data, 'name' => 'Disattivata', 'is_active' => false])->assertSessionHasNoErrors();
    $foreign = TransactionTemplate::factory()->create();

    $response = $this->getJson(route('transaction-templates.active'))->assertSuccessful()->assertJsonCount(2)
        ->assertJsonPath('0.category.uuid', $category->uuid)
        ->assertJsonPath('1.category.uuid', $category->uuid)
        ->assertJsonPath('0.category.icon', 'beef')
        ->assertJsonPath('0.category.color', '#047857')
        ->assertJsonPath('0.category.full_path', $category->displayName())
        ->assertJsonPath('0.account_name', $account->name)
        ->assertJsonPath('0.reference.name', 'Q8')
        ->assertJsonPath('0.reference.logo_url', route('tracked-item-logos.show', $identity->uuid))
        ->assertJsonPath('0.shared_enabled', false);

    expect(collect($response->json())->pluck('uuid'))->not->toContain($foreign->uuid);
    expect(Transaction::count())->toBe(0);

    $parent = Category::create(['user_id' => $user->id, 'name' => 'Auto', 'slug' => 'auto', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $category->update(['parent_id' => $parent->id]);
    $this->getJson(route('transaction-templates.active'))->assertSuccessful()
        ->assertJsonPath('0.category.full_path', 'Auto › '.$category->displayName());
});

test('a single use can change amount day account and disable shared without changing its template', function () {
    [$user, $account, $category, , $data] = quickTemplateFixture();
    $counterparty = TrackedItem::create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($user)->post(route('transaction-templates.store'), [...$data, 'shared_enabled' => true, 'shared_counterparty_uuid' => $counterparty->uuid])->assertSessionHasNoErrors();
    $template = TransactionTemplate::sole();
    $otherAccount = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [
        'transaction_template_uuid' => $template->uuid, 'transaction_day' => 12,
        'type_key' => 'expense', 'account_uuid' => $otherAccount->uuid,
        'category_uuid' => $category->uuid, 'amount' => '12.50', 'description' => 'Una sola registrazione',
        'is_shared_expense' => false,
    ])->assertSessionHasNoErrors();
    $transaction = Transaction::sole();
    expect($transaction->account_id)->toBe($otherAccount->id)
        ->and((float) $transaction->amount)->toBe(12.5)
        ->and($transaction->transaction_date->format('Y-m-d'))->toBe('2026-10-12')
        ->and($transaction->shared_expense_counterparty_id)->toBeNull()
        ->and($template->fresh()->shared_enabled)->toBeTrue()
        ->and($template->fresh()->account_id)->toBe($account->id)
        ->and($template->fresh()->default_amount)->toBeNull();
});

test('template catalog includes reference logos and structural category compatibility', function () {
    [$user, $account, $category, $reference] = quickTemplateFixture();
    $identity = WebsiteIdentity::factory()->create(['status' => 'ready', 'logo_path' => 'logos/q8.png']);
    $reference->update(['website_identity_id' => $identity->id, 'settings' => ['transaction_group_keys' => ['expense']]]);
    $reference->compatibleCategories()->sync([$category->id]);
    $this->actingAs($user)->get(route('transaction-templates.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('references', 1)
        ->where('references.0.logo_url', route('tracked-item-logos.show', $identity->uuid))
        ->where('references.0.category_uuids.0', $category->uuid)
        ->where('references.0.group_keys.0', 'expense')
        ->where('categories.0.group_key', 'expense'));
});

test('quick action configuration includes new owned categories and children even without templates', function () {
    [$user, , $category] = quickTemplateFixture();
    $this->actingAs($user)->get(route('transaction-templates.index'))->assertSuccessful();

    $newCategory = Category::create(['user_id' => $user->id, 'name' => 'Nuova categoria', 'slug' => 'nuova-categoria', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $child = Category::create(['user_id' => $user->id, 'parent_id' => $newCategory->id, 'name' => 'Sottocategoria', 'slug' => 'sottocategoria', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    Category::create(['user_id' => $user->id, 'name' => 'Disattivata', 'slug' => 'disattivata', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => false, 'is_selectable' => true]);
    Category::create(['user_id' => User::factory()->create()->id, 'name' => 'Altrui', 'slug' => 'altrui', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);

    $this->get(route('transaction-templates.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('categories', 3)
        ->where('categories', fn ($categories): bool => collect($categories)->pluck('uuid')->sort()->values()->all() === collect([$category->uuid, $newCategory->uuid, $child->uuid])->sort()->values()->all())
        ->where('categories', fn ($categories): bool => collect($categories)->firstWhere('uuid', $child->uuid)['parent_uuid'] === $newCategory->uuid)
        ->has('templates', 0));
    $this->getJson(route('transaction-templates.active'))->assertSuccessful()->assertJsonCount(0);
});

test('templates persist domain icons and custom colors and reject unsafe appearance input', function () {
    [$user, , , , $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), [...$data, 'icon' => 'utensils-crossed', 'color' => '#123ABC', 'notes' => 'Nota precompilata'])->assertSessionHasNoErrors();
    expect(TransactionTemplate::sole()->icon)->toBe('utensils-crossed')->and(TransactionTemplate::sole()->color)->toBe('#123ABC');
    expect(TransactionTemplate::sole()->prefill()['notes'])->toBe('Nota precompilata');
    $this->post(route('transaction-templates.store'), [...$data, 'color' => 'url(javascript:bad)'])->assertSessionHasErrors('color');
    $this->post(route('transaction-templates.store'), [...$data, 'icon' => '<svg>'])->assertSessionHasErrors('icon');
});

test('templates create edit deactivate and delete without any accounting records', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
    $template = TransactionTemplate::sole();
    expect($template->uuid)->not->toBeEmpty()->and($template->user_id)->toBe($user->id)->and($template->default_amount)->toBeNull()->and($template->usage_count)->toBe(0)->and(Transaction::count())->toBe(0)->and($account->fresh()->current_balance)->toEqual(1000);
    $this->patch(route('transaction-templates.update', $template->uuid), [...$data, 'name' => 'Benzina nuova'])->assertSessionHasNoErrors();
    expect($template->fresh()->name)->toBe('Benzina nuova')->and($template->fresh()->sort_order)->toBe(1);
    $this->patch(route('transaction-templates.toggle-active', $template->uuid))->assertRedirect();
    $this->getJson(route('transaction-templates.active'))->assertExactJson([]);
    $this->delete(route('transaction-templates.destroy', $template->uuid))->assertRedirect();
    $this->assertModelMissing($template);
});

test('template opening prefills normal transaction page and does not count usage', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), $data);
    $template = TransactionTemplate::sole();
    $this->get(route('transactions.show', ['year' => 2026, 'month' => 10, 'template' => $template->uuid]))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('transactions/Show')->where('transaction_template.name', 'Benzina')->where('transaction_template.account_uuid', $account->uuid)->where('transaction_template.category_uuid', $category->uuid)->where('transaction_template.tracked_item_uuid', $reference->uuid)->where('transaction_template.default_amount', null));
    expect($template->fresh()->usage_count)->toBe(0)->and($template->fresh()->last_used_at)->toBeNull();
});

test('normal transaction endpoint counts template only after a successful save', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), $data);
    $template = TransactionTemplate::sole();
    $payload = ['transaction_template_uuid' => $template->uuid, 'transaction_day' => 9, 'type_key' => 'expense', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'tracked_item_uuid' => $reference->uuid, 'description' => 'Rifornimento', 'amount' => '25.00'];
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$payload, 'amount' => 'bad'])->assertSessionHasErrors();
    expect($template->fresh()->usage_count)->toBe(0)->and(Transaction::count())->toBe(0);
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), $payload)->assertSessionHasNoErrors();
    expect(Transaction::sole()->amount)->toEqual(25)->and($account->fresh()->current_balance)->toEqual(975)->and($template->fresh()->usage_count)->toBe(1)->and($template->fresh()->last_used_at)->not->toBeNull();
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), $payload)->assertSessionHasErrors();
    expect(Transaction::count())->toBe(1)->and($template->fresh()->usage_count)->toBe(1);
});

test('shared templates use normal self or counterparty accounting behavior', function (string $payer) {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $counterparty = TrackedItem::create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => 'emanuela', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($user)->post(route('transaction-templates.store'), [...$data, 'shared_enabled' => true, 'shared_counterparty_uuid' => $counterparty->uuid, 'shared_payer' => $payer]);
    $template = TransactionTemplate::sole();
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), ['transaction_template_uuid' => $template->uuid, 'transaction_day' => 9, 'type_key' => 'expense', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'amount' => '100.00', 'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $counterparty->uuid, 'shared_expense_paid_by' => $payer, 'shared_expense_my_share' => '50.00', 'shared_expense_counterparty_share' => '50.00'])->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe($payer === 'self' ? 1 : 0)->and(SharedExpenseLedgerEntry::count())->toBe($payer === 'counterparty' ? 1 : 0)->and($account->fresh()->current_balance)->toEqual($payer === 'self' ? 900 : 1000)->and($template->fresh()->usage_count)->toBe(1);
})->with(['self', 'counterparty']);

test('shared transaction and template positions immediately update credits debts through settlements', function (string $payer, bool $useTemplate) {
    config(['features.credits_debts.enabled' => true]);
    [$user, $account, $category, , $data] = quickTemplateFixture();
    $counterparty = TrackedItem::create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($user);
    $payload = ['transaction_day' => 9, 'type_key' => 'expense', 'account_uuid' => $account->uuid,
        'category_uuid' => $category->uuid, 'amount' => '100.00', 'is_shared_expense' => true,
        'shared_expense_counterparty_uuid' => $counterparty->uuid, 'shared_expense_paid_by' => $payer,
        'shared_expense_my_share' => '50.00', 'shared_expense_counterparty_share' => '50.00'];
    if ($useTemplate) {
        $this->post(route('transaction-templates.store'), [...$data, 'shared_enabled' => true,
            'shared_counterparty_uuid' => $counterparty->uuid, 'shared_payer' => $payer])->assertSessionHasNoErrors();
        $payload['transaction_template_uuid'] = TransactionTemplate::sole()->uuid;
    }
    $url = route('credits-debts.index', ['year' => 2026, 'month' => 10]);
    $this->getJson($url)->assertJsonCount(0, 'data');
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), $payload)->assertSessionHasNoErrors();
    $side = $payer === 'self' ? 'credits' : 'debts';
    $this->get($url)->assertInertia(fn (Assert $page) => $page->has('items', 1)
        ->where('items.0.origin', 'shared')->where('items.0.read_only', true)
        ->where('items.0.remaining_amount', '50.00')->where('summary.'.$side.'_remaining_total', '50.00'));
    expect(Transaction::count())->toBe($payer === 'self' ? 1 : 0)
        ->and(CreditDebtItem::count())->toBe(0);
    $this->actingAs(User::factory()->create())->getJson($url)->assertJsonCount(0, 'data');
    $this->actingAs($user);
    foreach (['20.00' => '30.00', '30.00' => '0.00'] as $amount => $remaining) {
        app(SharedExpenseSettlementService::class)->record($user, $counterparty, [
            'amount' => $amount, 'settled_at' => '2026-10-10', 'account_uuid' => $account->uuid, 'reference' => null,
        ]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('items', $remaining === '0.00' ? 0 : 1)
            ->where('summary.'.$side.'_remaining_total', $remaining));
    }
    expect(CreditDebtItem::count())->toBe(0);
})->with(['user paid' => ['self'], 'counterparty paid' => ['counterparty']])->with(['normal' => [false], 'quick action' => [true]]);

test('unavailable references are explicitly reported and require replacement', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), $data);
    $template = TransactionTemplate::sole();
    $reference->delete();
    expect($template->fresh()->prefill()['issues']['tracked_item'])->toContain('Q8')->and($template->fresh()->prefill()['tracked_item_uuid'])->toBeNull();
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), ['transaction_template_uuid' => $template->uuid, 'transaction_day' => 9, 'type_key' => 'expense', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'amount' => '25.00'])->assertSessionHasErrors('tracked_item_uuid');
    expect(Transaction::count())->toBe(0)->and($template->fresh()->usage_count)->toBe(0);
});

test('templates and references remain isolated between users', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $other = User::factory()->create();
    UserYear::create(['user_id' => $other->id, 'year' => 2026, 'is_closed' => false]);
    $this->actingAs($user)->post(route('transaction-templates.store'), $data);
    $template = TransactionTemplate::sole();
    $this->actingAs($other)->getJson(route('transaction-templates.active'))->assertExactJson([]);
    $this->patch(route('transaction-templates.update', $template->uuid), [...$data, 'account_uuid' => null, 'category_uuid' => null, 'tracked_item_uuid' => null])->assertNotFound();
    $this->delete(route('transaction-templates.destroy', $template->uuid))->assertNotFound();
    $this->patch(route('transaction-templates.toggle-active', $template->uuid))->assertNotFound();
    $this->post(route('transaction-templates.store'), $data)->assertSessionHasErrors(['account_uuid', 'category_uuid', 'tracked_item_uuid']);
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), ['transaction_template_uuid' => $template->uuid, 'transaction_day' => 9, 'type_key' => 'expense', 'amount' => '25.00'])->assertSessionHasErrors('transaction_template_uuid');
});

test('templates persist custom percentage independently from any transaction amount', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $counterparty = TrackedItem::create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($user)->post(route('transaction-templates.store'), [...$data, 'shared_enabled' => true, 'shared_counterparty_uuid' => $counterparty->uuid, 'shared_split_mode' => 'percentage', 'my_share_basis_points' => 3000])->assertSessionHasNoErrors();
    expect(TransactionTemplate::sole()->prefill()['my_share_basis_points'])->toBe(3000)->and(TransactionTemplate::sole()->default_amount)->toBeNull();
    $this->post(route('transaction-templates.store'), [...$data, 'my_share_basis_points' => 10001])->assertSessionHasErrors('my_share_basis_points');
});

test('inactive and foreign template references cannot silently fall back', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), $data)->assertSessionHasNoErrors();
    $template = TransactionTemplate::sole();
    $account->update(['is_active' => false]);
    $category->update(['is_active' => false]);
    $reference->update(['is_active' => false]);
    $prefill = $template->fresh()->prefill();
    expect($prefill['account_uuid'])->toBeNull()->and($prefill['category_uuid'])->toBeNull()->and($prefill['tracked_item_uuid'])->toBeNull()->and(array_keys($prefill['issues']))->toBe(['account', 'category', 'tracked_item']);
    $this->post(route('transaction-templates.store'), $data)->assertSessionHasErrors(['account_uuid', 'category_uuid', 'tracked_item_uuid']);
    $template->update(['is_active' => false]);
    $this->get(route('transactions.show', ['year' => 2026, 'month' => 10, 'template' => $template->uuid]))->assertNotFound();
});

test('incompatible category and reference combinations are rejected by template validation', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $reference->update(['settings' => ['transaction_group_keys' => ['income']]]);
    $this->actingAs($user)->post(route('transaction-templates.store'), $data)->assertSessionHasErrors('tracked_item_uuid');
    $this->post(route('transaction-templates.store'), [...$data, 'direction' => 'income'])->assertSessionHasErrors('category_uuid');
    expect(TransactionTemplate::count())->toBe(0);
});

test('quick actions keep sort order on create and persist drag and drop reordering', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), [...$data, 'name' => 'Benzina']);
    $this->actingAs($user)->post(route('transaction-templates.store'), [...$data, 'name' => 'Spesa']);
    $first = TransactionTemplate::where('name', 'Benzina')->sole();
    $second = TransactionTemplate::where('name', 'Spesa')->sole();
    expect($first->sort_order)->toBe(1)->and($second->sort_order)->toBe(2);
    $this->patch(route('transaction-templates.reorder'), ['uuids' => [$first->uuid]])->assertUnprocessable();
    $this->patch(route('transaction-templates.reorder'), ['uuids' => [$second->uuid, $first->uuid]])->assertRedirect()->assertSessionHasNoErrors();
    expect($second->fresh()->sort_order)->toBe(0)->and($first->fresh()->sort_order)->toBe(1);
    $this->get(route('transaction-templates.index'))->assertInertia(fn (Assert $page) => $page->component('settings/QuickActions')->where('templates.0.uuid', $second->uuid)->where('templates.1.uuid', $first->uuid));
});

test('a posting failure never counts template usage', function () {
    [$user, $account, $category, $reference, $data] = quickTemplateFixture();
    $this->actingAs($user)->post(route('transaction-templates.store'), $data);
    $template = TransactionTemplate::sole();
    $this->mock(TransactionMutationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('store')->once()->andThrow(new ValidationException(validator([], [])));
    });
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), ['transaction_template_uuid' => $template->uuid, 'transaction_day' => 9, 'type_key' => 'expense', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'amount' => '25.00'])->assertRedirect();
    expect($template->fresh()->usage_count)->toBe(0)->and($template->fresh()->last_used_at)->toBeNull()->and(Transaction::count())->toBe(0)->and($account->fresh()->current_balance)->toEqual(1000);
});
