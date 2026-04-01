<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\RecurringEntryLifecycleService;
use App\Services\Recurring\RecurringEntryManagementService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('store recurring entry creates the plan and generates initial occurrences', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
                'start_date' => '2026-01-15',
                'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
                'occurrences_limit' => 3,
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHas('success', __('transactions.flash.recurring_created'));

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->title)->toBe('Rent recurring entry')
        ->and($entry->occurrences)->toHaveCount(3)
        ->and($entry->occurrences->pluck('status')->map->value->all())->toBe(['pending', 'pending', 'pending']);
});

test('store recurring entry rejects an end date earlier than the start date', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
                'start_date' => '2026-03-20',
                'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
                'end_date' => '2026-03-10',
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHasErrors([
            'end_date' => __('transactions.validation.recurring_end_date_after_start_date'),
        ]);
});

test('store installment entry creates the plan and generates installment occurrences with correct amounts', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => 1000,
            'installments_count' => 3,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences)->toHaveCount(3)
        ->and($entry->occurrences->pluck('expected_amount')->all())->toBe(['333.33', '333.33', '333.34']);
});

test('index returns recurring entries and supports filters and sorting', function () {
    $context = recurringManagementContext();
    $context['category']->update([
        'icon' => 'house',
        'color' => '#0f766e',
    ]);
    $context['account']->update(['is_default' => true]);
    $first = createManagedRecurringEntry($context, [
        'title' => 'Beta rent',
        'next_occurrence_date' => '2026-03-01',
        'auto_create_transaction' => false,
    ]);
    $second = createManagedRecurringEntry($context, [
        'title' => 'Alpha rent',
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 500,
        'installments_count' => 5,
        'next_occurrence_date' => '2026-02-01',
        'auto_create_transaction' => true,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'auto_create_transaction' => 1,
            'year' => 2026,
            'month' => 3,
            'sort' => 'title',
            'direction_sort' => 'asc',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/recurring/Index')
            ->where('activePeriod.year', 2026)
            ->where('activePeriod.month', 3)
            ->where('dateOptions.available_years', [2026])
            ->where('dateOptions.max', now()->toDateString())
            ->where('formOptions.default_account_uuid', $context['account']->uuid)
            ->where('filters.entry_type', RecurringEntryTypeEnum::INSTALLMENT->value)
            ->where('filters.account_id', null)
            ->where('monthlyCalendar.month', 3)
            ->where('monthlyCalendar.summary.entries_count', 1)
            ->has('formOptions.accounts')
            ->has('formOptions.filter_accounts')
            ->has('formOptions.entry_types', 2)
            ->has('monthlyCalendar.days', 1)
            ->where('monthlyCalendar.days.0.date', '2026-03-15')
            ->where('monthlyCalendar.days.0.anchor', 'occurrence-day-2026-03-15')
            ->where('monthlyCalendar.days.0.occurrences_count', 1)
            ->where('monthlyCalendar.days.0.expense_total', 100)
            ->missing('recurringEntries.0.id')
            ->missing('recurringEntries.0.account.id')
            ->missing('monthlyCalendar.days.0.occurrences.0.id')
            ->where('formOptions.accounts', fn ($accounts) => count($accounts) > 0
                && is_string($accounts[0]['value'])
                && ! array_key_exists('id', $accounts[0]))
            ->where("formOptions.categories.{$context['account']->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($item) => $item['value'] === $context['category']->uuid
                    && $item['icon'] === 'house'
                    && $item['color'] === '#0f766e'
                    && $item['full_path'] === 'Rent'
                    && $item['is_selectable'] === true))
            ->where('recurringEntries', fn ($entries) => count($entries) === 1
                && $entries[0]['uuid'] === $second->uuid
                && $entries[0]['title'] === 'Alpha rent'
                && $entries[0]['stats']['total_occurrences'] === 5)
            ->where('transactionsNavigation', fn ($navigation) => $navigation['context']['month'] === 3
                && str_contains($navigation['months'][2]['href'], '/recurring-entries'))
        );

    expect($first->uuid)->not->toBe($second->uuid);
});

test('store recurring entry rejects future and unavailable recurring dates', function () {
    $context = recurringManagementContext();

    UserYear::query()->where('user_id', $context['user']->id)->delete();
    UserYear::query()->create([
        'user_id' => $context['user']->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'start_date' => '2099-05-15',
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHasErrors('start_date');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'start_date' => '2025-12-31',
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHasErrors('start_date');
});

test('index recurring date options clamp the max date to the last active year when current year is unavailable', function () {
    $context = recurringManagementContext();

    UserYear::query()->where('user_id', $context['user']->id)->delete();
    UserYear::query()->create([
        'user_id' => $context['user']->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2025,
            'month' => 12,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dateOptions.available_years', [2025])
            ->where('dateOptions.min', '2025-01-01')
            ->where('dateOptions.max', '2025-12-31')
        );
});

test('index supports cancelled status filter', function () {
    $context = recurringManagementContext();
    createManagedRecurringEntry($context, [
        'title' => 'Active recurring',
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
    ]);
    $cancelled = createManagedRecurringEntry($context, [
        'title' => 'Cancelled recurring',
        'status' => RecurringEntryStatusEnum::CANCELLED->value,
        'is_active' => false,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'status' => RecurringEntryStatusEnum::CANCELLED->value,
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', RecurringEntryStatusEnum::CANCELLED->value)
            ->where('recurringEntries', fn ($entries) => count($entries) === 1
                && $entries[0]['uuid'] === $cancelled->uuid
                && $entries[0]['status'] === RecurringEntryStatusEnum::CANCELLED->value)
        );
});

test('show returns recurring entry details occurrences and summary payload', function () {
    $context = recurringManagementContext();
    $context['account']->update(['is_default' => true]);
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 300,
        'installments_count' => 3,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/recurring/Show')
            ->where('dateOptions.available_years', [2026])
            ->where('dateOptions.max', now()->toDateString())
            ->where('formOptions.default_account_uuid', $context['account']->uuid)
            ->where('recurringEntry.entry.uuid', $entry->uuid)
            ->where('recurringEntry.entry.stats.total_occurrences', 3)
            ->where('recurringEntry.summary.remaining_amount', 300)
            ->missing('recurringEntry.entry.id')
            ->missing('recurringEntry.occurrences.0.id')
            ->missing('recurringEntry.occurrences.0.converted_transaction_id')
            ->where('formOptions.accounts', fn ($accounts) => count($accounts) > 0
                && is_string($accounts[0]['value'])
                && ! array_key_exists('id', $accounts[0]))
            ->where('recurringEntry.occurrences', fn ($occurrences) => count($occurrences) === 3
                && $occurrences[0]['sequence_number'] === 1
                && array_key_exists('can_convert', $occurrences[0]))
            ->where('recurringEntry.actions.can_cancel', true)
        );
});

test('show orders occurrences chronologically by date', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 300,
        'installments_count' => 3,
    ]);

    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    $occurrences[0]->update([
        'expected_date' => '2026-01-20',
        'due_date' => '2026-01-20',
    ]);
    $occurrences[1]->update([
        'expected_date' => '2026-01-05',
        'due_date' => '2026-01-05',
    ]);
    $occurrences[2]->update([
        'expected_date' => '2026-01-12',
        'due_date' => '2026-01-12',
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.due_date', '2026-01-05')
            ->where('recurringEntry.occurrences.1.due_date', '2026-01-12')
            ->where('recurringEntry.occurrences.2.due_date', '2026-01-20')
        );
});

test('user sees recurring entries from accessible shared accounts but not revoked memberships', function () {
    $context = recurringManagementContext();
    $sharedOwnerContext = recurringManagementContext();
    $revokedOwnerContext = recurringManagementContext();

    $sharedEntry = createManagedRecurringEntry($sharedOwnerContext, [
        'title' => 'Shared recurring visible',
    ]);

    createManagedRecurringEntry($revokedOwnerContext, [
        'title' => 'Shared recurring hidden',
    ]);

    shareRecurringAccount($sharedOwnerContext['account'], $context['user'], AccountMembershipRoleEnum::VIEWER);
    shareRecurringAccount($revokedOwnerContext['account'], $context['user'], AccountMembershipRoleEnum::VIEWER, AccountMembershipStatusEnum::REVOKED);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($entry) => $entry['uuid'] === $sharedEntry->uuid && $entry['title'] === 'Shared recurring visible'))
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->doesntContain(fn ($entry) => $entry['title'] === 'Shared recurring hidden')));
});

test('viewer can read shared recurring entries but cannot mutate them', function () {
    $context = recurringManagementContext();
    $ownerContext = recurringManagementContext();
    $entry = createManagedRecurringEntry($ownerContext, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    shareRecurringAccount($ownerContext['account'], $context['user'], AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.entry.uuid', $entry->uuid)
            ->where('recurringEntry.entry.can_edit', false)
            ->where('recurringEntry.actions.can_pause', false)
            ->where('recurringEntry.actions.can_resume', false)
            ->where('recurringEntry.actions.can_cancel', false)
            ->where('recurringEntry.occurrences.0.can_convert', false)
            ->where('recurringEntry.occurrences.0.can_skip', false)
            ->where('recurringEntry.occurrences.0.can_cancel', false));

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($ownerContext, [
            'title' => 'Viewer should not update',
        ]))
        ->assertSessionHasErrors('entry');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Viewer should not create',
        ]))
        ->assertSessionHasErrors('account_id');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertSessionHasErrors('occurrence');
});

test('shared recurring form options expose accessible filter accounts and operational owner plus editor datasets while excluding viewers', function () {
    $viewerContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();
    $editorContributor = recurringManagementContext();
    $viewerContributor = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $viewerContext['user'], AccountMembershipRoleEnum::VIEWER);
    shareRecurringAccount($ownerContext['account'], $editorContributor['user'], AccountMembershipRoleEnum::EDITOR);
    shareRecurringAccount($ownerContext['account'], $viewerContributor['user'], AccountMembershipRoleEnum::VIEWER);

    $editorScope = Scope::query()->create([
        'user_id' => $editorContributor['user']->id,
        'name' => 'Editor shared scope',
        'type' => 'household',
        'color' => '#112233',
        'is_active' => true,
    ]);
    $viewerScope = Scope::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer hidden scope',
        'type' => 'household',
        'color' => '#445566',
        'is_active' => true,
    ]);
    $editorCategory = Category::query()->create([
        'user_id' => $editorContributor['user']->id,
        'name' => 'Editor shared category',
        'slug' => 'editor-shared-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $viewerCategory = Category::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer hidden category',
        'slug' => 'viewer-hidden-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $editorTrackedItem = TrackedItem::query()->create([
        'user_id' => $editorContributor['user']->id,
        'name' => 'Editor shared tracked item',
        'slug' => 'editor-shared-tracked-item-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);
    $viewerTrackedItem = TrackedItem::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer hidden tracked item',
        'slug' => 'viewer-hidden-tracked-item-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);

    $this->actingAs($viewerContext['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.filter_accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['value'] === (string) $ownerContext['account']->id
                    && $account['is_shared'] === true
                    && $account['can_edit'] === false))
            ->where('formOptions.accounts', fn ($accounts) => collect($accounts)
                ->doesntContain(fn ($account) => $account['value'] === $ownerContext['account']->uuid))
        );

    $this->actingAs($editorContributor['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['value'] === $ownerContext['account']->uuid
                    && $account['is_shared'] === true
                    && $account['membership_role'] === AccountMembershipRoleEnum::EDITOR->value
                    && in_array($ownerContext['user']->id, $account['category_contributor_user_ids'], true)
                    && in_array($editorContributor['user']->id, $account['category_contributor_user_ids'], true)
                    && ! in_array($viewerContributor['user']->id, $account['category_contributor_user_ids'], true)))
            ->where('formOptions.scopes', fn ($scopes) => collect($scopes)->contains('label', $ownerContext['scope']->name)
                && collect($scopes)->contains('label', $editorScope->name)
                && collect($scopes)->doesntContain('label', $viewerScope->name))
            ->where('formOptions.categories', fn ($categories) => collect($categories)->has($ownerContext['account']->uuid)
                && collect(collect($categories)->get($ownerContext['account']->uuid, []))->doesntContain(
                    fn ($category) => str_contains($category['label'], $viewerCategory->name)
                ))
            ->where('formOptions.tracked_items', fn ($trackedItems) => collect($trackedItems)->has($ownerContext['account']->uuid)
                && collect(collect($trackedItems)->get($ownerContext['account']->uuid, []))->doesntContain(
                    fn ($trackedItem) => str_contains($trackedItem['label'], $viewerTrackedItem->name)
                ))
        );
});

test('editor can create and mutate recurring entries on shared accounts with owner dataset preserved', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared editor recurring',
            'tracked_item_id' => null,
            'start_date' => '2026-02-15',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()
        ->where('title', 'Shared editor recurring')
        ->firstOrFail();

    expect($entry->user_id)->toBe($ownerContext['user']->id)
        ->and($entry->created_by_user_id)->toBe($editorContext['user']->id)
        ->and($entry->updated_by_user_id)->toBe($editorContext['user']->id);

    $this->actingAs($editorContext['user'])
        ->patch(route('recurring-entries.pause', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::PAUSED);

    $this->actingAs($editorContext['user'])
        ->patch(route('recurring-entries.resume', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($editorContext['user'])
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($ownerContext, [
            'title' => 'Shared editor updated',
            'tracked_item_id' => null,
            'description' => 'Updated by shared editor',
            'start_date' => '2026-02-15',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
        ]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->title)->toBe('Shared editor updated')
        ->and($entry->fresh()->updated_by_user_id)->toBe($editorContext['user']->id);
});

test('owner sees recurring entries created by an invitee on a shared account', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Invitee shared recurring visible to owner',
            'tracked_item_id' => null,
            'start_date' => '2026-02-15',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()
        ->where('title', 'Invitee shared recurring visible to owner')
        ->firstOrFail();

    $this->actingAs($ownerContext['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($candidate) => $candidate['uuid'] === $entry->uuid && $candidate['title'] === 'Invitee shared recurring visible to owner'))
        );
});

test('invitee sees recurring entries created by the owner on a shared account', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $entry = createManagedRecurringEntry($ownerContext, [
        'title' => 'Owner shared recurring visible to invitee',
        'tracked_item_id' => null,
        'start_date' => '2026-02-15',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $this->actingAs($editorContext['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($candidate) => $candidate['uuid'] === $entry->uuid && $candidate['title'] === 'Owner shared recurring visible to invitee'))
        );
});

test('editor can create shared recurring entries using shared account scoped categories and tracked items while keeping scopes contributor aware', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();
    $viewerContributor = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);
    shareRecurringAccount($ownerContext['account'], $viewerContributor['user'], AccountMembershipRoleEnum::VIEWER);

    $editorScope = Scope::query()->create([
        'user_id' => $editorContext['user']->id,
        'name' => 'Editor recurring scope',
        'type' => 'household',
        'color' => '#123456',
        'is_active' => true,
    ]);
    $sharedCategory = Category::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring category',
        'slug' => 'shared-recurring-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $sharedTrackedItem = TrackedItem::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring tracked item',
        'slug' => 'shared-recurring-tracked-item-'.fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [TransactionDirectionEnum::EXPENSE->value],
            'transaction_category_uuids' => [$sharedCategory->uuid],
        ],
    ]);
    $sharedTrackedItem->compatibleCategories()->sync([$sharedCategory->id]);
    $viewerScope = Scope::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer recurring scope',
        'type' => 'household',
        'color' => '#654321',
        'is_active' => true,
    ]);

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared owner scope recurring',
            'scope_id' => $ownerContext['scope']->id,
            'category_id' => $sharedCategory->id,
            'tracked_item_id' => $sharedTrackedItem->id,
        ]))
        ->assertRedirect();

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared editor scope recurring',
            'scope_id' => $editorScope->id,
            'category_id' => $sharedCategory->id,
            'tracked_item_id' => $sharedTrackedItem->id,
        ]))
        ->assertRedirect();

    $this->actingAs($editorContext['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared viewer scope recurring',
            'scope_id' => $viewerScope->id,
            'category_id' => $sharedCategory->id,
            'tracked_item_id' => $sharedTrackedItem->id,
        ]))
        ->assertSessionHasErrors('scope_id');

    expect(RecurringEntry::query()->where('title', 'Shared owner scope recurring')->exists())->toBeTrue()
        ->and(RecurringEntry::query()->where('title', 'Shared editor scope recurring')->exists())->toBeTrue()
        ->and(RecurringEntry::query()->where('title', 'Shared viewer scope recurring')->exists())->toBeFalse();
});

test('recurring editor can create a new tracked item option on a shared account and receive an account scoped reference immediately', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editorContext['user'])
        ->postJson(route('recurring-entries.tracked-items.store'), [
            'name' => 'Shared recurring reference',
            'account_uuid' => $ownerContext['account']->uuid,
            'category_uuid' => $ownerContext['category']->uuid,
            'direction' => TransactionDirectionEnum::EXPENSE->value,
        ])
        ->assertOk()
        ->assertJsonPath('item.label', 'Shared recurring reference')
        ->assertJsonPath('item.account_uuid', $ownerContext['account']->uuid);

    $trackedItem = TrackedItem::query()
        ->where('account_id', $ownerContext['account']->id)
        ->where('name', 'Shared recurring reference')
        ->firstOrFail();

    expect((int) $trackedItem->account_id)->toBe($ownerContext['account']->id)
        ->and((int) $trackedItem->user_id)->toBe($ownerContext['account']->user_id)
        ->and($trackedItem->settings['shared_account_uuid'] ?? null)->toBe($ownerContext['account']->uuid)
        ->and($trackedItem->settings['transaction_category_uuids'] ?? [])->not->toBe([]);
});

test('recurring form tracked item creation reuses an existing personal reference without duplicates and keeps it visible in the personal account catalog', function () {
    $context = recurringManagementContext();

    TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => null,
        'name' => 'Recurring gym',
        'slug' => 'recurring-gym',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [TransactionDirectionEnum::EXPENSE->value],
            'transaction_category_uuids' => [$context['category']->uuid],
        ],
    ])->compatibleCategories()->sync([$context['category']->id]);

    $this->actingAs($context['user'])
        ->postJson(route('recurring-entries.tracked-items.store'), [
            'name' => 'Recurring gym',
            'account_uuid' => $context['account']->uuid,
            'category_uuid' => $context['category']->uuid,
            'direction' => TransactionDirectionEnum::EXPENSE->value,
        ])
        ->assertOk()
        ->assertJsonPath('item.label', 'Recurring gym')
        ->assertJsonPath('item.account_uuid', $context['account']->uuid);

    expect(TrackedItem::query()
        ->ownedBy($context['user']->id)
        ->where('slug', 'recurring-gym')
        ->count())->toBe(1);
});

test('shared recurring show payload keeps account scoped categories and tracked items on update flows without personal fallback', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $sharedCategory = Category::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring category',
        'slug' => 'shared-recurring-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $sharedTrackedItem = TrackedItem::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring item',
        'slug' => 'shared-recurring-item-'.fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [TransactionDirectionEnum::EXPENSE->value],
            'transaction_category_uuids' => [$sharedCategory->uuid],
        ],
    ]);
    $sharedTrackedItem->compatibleCategories()->sync([$sharedCategory->id]);
    $personalTrackedItem = TrackedItem::query()->create([
        'user_id' => $editorContext['user']->id,
        'account_id' => null,
        'name' => 'Editor personal hidden item',
        'slug' => 'editor-personal-hidden-item-'.fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
    ]);

    $entry = createManagedRecurringEntry($ownerContext, [
        'category_id' => $sharedCategory->id,
        'tracked_item_id' => $sharedTrackedItem->id,
    ]);

    $this->actingAs($editorContext['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.entry.tracked_item.uuid', $sharedTrackedItem->uuid)
            ->where('formOptions.categories', fn ($categories) => collect($categories)->has($ownerContext['account']->uuid))
            ->where('formOptions.tracked_items', fn ($trackedItems) => collect(collect($trackedItems)->get($ownerContext['account']->uuid, []))
                ->contains(fn ($trackedItem) => $trackedItem['label'] === $sharedTrackedItem->name)
                && collect(collect($trackedItems)->get($ownerContext['account']->uuid, []))->doesntContain(
                    fn ($trackedItem) => str_contains($trackedItem['label'], $personalTrackedItem->name)
                ))
        );
});

test('store recurring automatic entry with start date today creates the first transaction immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-03-23',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => true,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(1)
        ->and(Transaction::query()->where('recurring_entry_occurrence_id', $entry->occurrences()->firstOrFail()->id)->count())->toBe(1);
});

test('store installment automatic entry with start date today creates the first transaction immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-03-23',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'total_amount' => 1200,
            'installments_count' => 3,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(1);
});

test('store recurring automatic entry with past start date creates all matured transactions immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-01-23',
            'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 5,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(3)
        ->and(Transaction::query()->whereNotNull('recurring_entry_occurrence_id')->count())->toBe(3);
});

test('store installment automatic entry with past start date creates all matured installments immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-01-23',
            'total_amount' => 900,
            'installments_count' => 5,
            'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(3);
});

test('store recurring manual entry with start date today does not create transactions automatically', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-03-23',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => false,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
        ]))
        ->assertRedirect();

    expect(Transaction::query()->count())->toBe(0);
});

test('store installment manual entry with past start date does not create transactions automatically', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-01-23',
            'total_amount' => 900,
            'installments_count' => 5,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => false,
        ]))
        ->assertRedirect();

    expect(Transaction::query()->count())->toBe(0);
});

test('initial automatic posting remains idempotent on a second lifecycle synchronization', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-01-23',
            'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 5,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    app(RecurringEntryLifecycleService::class)
        ->synchronize($entry->fresh(), CarbonImmutable::parse('2026-03-23'));

    expect(Transaction::query()->whereNotNull('recurring_entry_occurrence_id')->count())->toBe(3);
});

test('update allows structural changes when the plan has no converted occurrences', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'start_date' => '2026-01-15',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $newCategory = Category::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Mutuo',
        'slug' => 'mutuo-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->patch(route('recurring-entries.update', $entry->uuid), [
            ...recurringManagementPayload($context, [
                'category_id' => $newCategory->id,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
                'start_date' => '2026-02-01',
                'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
                'occurrences_limit' => 3,
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHas('success', __('transactions.flash.recurring_updated'));

    $entry->refresh();

    expect($entry->category_id)->toBe($newCategory->id)
        ->and($entry->occurrences)->toHaveCount(3)
        ->and($entry->occurrences->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-02-01', '2026-02-02', '2026-02-03']);
});

test('converted occurrence transaction link points to the matching transactions month with highlight query', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'transaction_date' => '2026-01-15',
        ])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.converted_transaction.show_url', function (string $url) use ($occurrence): bool {
                $transactionUuid = $occurrence->fresh()->convertedTransaction?->uuid;

                return str_contains($url, '/transactions/2026/1')
                    && str_contains($url, 'highlight='.$transactionUuid)
                    && str_contains($url, 'source=recurring');
            })
        );
});

test('index recurring payload exposes linked transaction path with highlight query', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $transactionUuid = $occurrence->fresh()->convertedTransaction?->uuid;

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlyCalendar.days.0.occurrences.0.converted_transaction.show_url', function (?string $url) use ($transactionUuid): bool {
                return is_string($url)
                    && str_contains($url, '/transactions/2026/1')
                    && str_contains($url, 'highlight='.$transactionUuid)
                    && str_contains($url, 'source=recurring');
            })
        );
});

test('update blocks structural changes when the plan has converted occurrences but allows non destructive fields', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($context, [
            'title' => 'Updated visible title',
            'account_id' => $context['secondAccount']->id,
        ]))
        ->assertSessionHasErrors('entry');

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($context, [
            'title' => 'Updated visible title',
            'description' => 'Updated description',
            'notes' => 'Updated notes',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
            'auto_create_transaction' => true,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $entry->refresh();

    expect($entry->title)->toBe('Updated visible title')
        ->and($entry->account_id)->toBe($context['account']->id)
        ->and($entry->auto_create_transaction)->toBeTrue();
});

test('pause resume and cancel update the plan state and future occurrences coherently', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
    ]);

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.pause', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::PAUSED)
        ->and($entry->fresh()->is_active)->toBeFalse();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.resume', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::ACTIVE)
        ->and($entry->fresh()->is_active)->toBeTrue();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.cancel', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::CANCELLED)
        ->and($entry->fresh()->next_occurrence_date)->toBeNull()
        ->and($entry->fresh()->occurrences->pluck('status')->map->value->all())->toBe(['cancelled', 'cancelled', 'cancelled']);
});

test('convert occurrence creates a scheduled transaction and does not duplicate on second conversion', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'transaction_date' => '2026-01-17',
        ])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect(Transaction::query()->where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1)
        ->and($occurrence->fresh()->convertedTransaction?->kind)->toBe(TransactionKindEnum::SCHEDULED);
});

test('future occurrence conversion requires explicit confirmation', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();
    $occurrence->forceFill([
        'expected_date' => '2099-05-15',
        'due_date' => null,
    ])->save();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('occurrence');

    expect($occurrence->fresh()->converted_transaction_id)->toBeNull();
});

test('undo conversion permanently removes the scheduled transaction and resets the occurrence', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $transaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->delete(route('recurring-entries.occurrences.undo-conversion', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHas('success', __('transactions.flash.recurring_conversion_undone'));

    expect(Transaction::withTrashed()->where('uuid', $transaction->uuid)->exists())->toBeFalse()
        ->and($occurrence->fresh()->converted_transaction_id)->toBeNull()
        ->and($occurrence->fresh()->status->value)->toBe('pending');
});

test('undo conversion is blocked when the scheduled transaction has already been refunded', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $scheduledTransaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $scheduledTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->delete(route('recurring-entries.occurrences.undo-conversion', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('occurrence');

    expect($occurrence->fresh()->converted_transaction_id)->not->toBeNull();
});

test('refund from recurring is available only for the latest converted occurrence', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
    ]);
    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    foreach ($occurrences as $occurrence) {
        $this->actingAs($context['user'])
            ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
                'confirm_future_date' => true,
            ])
            ->assertRedirect(route('recurring-entries.show', $entry->uuid));
    }

    $firstTransaction = $occurrences[0]->fresh()->convertedTransaction;
    $lastTransaction = $occurrences[2]->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $firstTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('transaction');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $lastTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionDoesntHaveErrors();
});

test('undo conversion is blocked for occurrences linked to non scheduled transactions', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $manualTransaction = Transaction::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => $context['account']->id,
        'category_id' => $context['category']->id,
        'tracked_item_id' => $context['trackedItem']->id,
        'transaction_date' => '2026-01-15',
        'value_date' => '2026-01-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 120,
        'currency' => 'EUR',
        'description' => 'Manual override',
        'source_type' => 'manual',
        'status' => 'confirmed',
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    $occurrence->update([
        'converted_transaction_id' => $manualTransaction->id,
        'status' => 'completed',
    ]);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->delete(route('recurring-entries.occurrences.undo-conversion', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('occurrence');

    expect(Transaction::query()->whereKey($manualTransaction->id)->exists())->toBeTrue()
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($manualTransaction->id);
});

test('refund endpoint refunds eligible transactions and blocks forbidden cases', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $scheduledTransaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $scheduledTransaction->uuid), [])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect(Transaction::query()->where('refunded_transaction_id', $scheduledTransaction->id)->count())->toBe(1);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.converted_transaction.is_refunded', true)
            ->where('recurringEntry.occurrences.0.status', 'refunded')
        );

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $scheduledTransaction->uuid), [])
        ->assertSessionHasErrors('transaction');

    $refundTransaction = Transaction::query()->where('refunded_transaction_id', $scheduledTransaction->id)->firstOrFail();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $refundTransaction->uuid), [])
        ->assertSessionHasErrors('transaction');

    $openingBalance = Transaction::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => $context['account']->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => '1000.00',
        'currency' => 'EUR',
        'description' => 'Opening',
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $openingBalance->uuid), [])
        ->assertSessionHasErrors('transaction');
});

function recurringManagementContext(): array
{
    $user = User::factory()->create();

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'payment_account',
    ], [
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Primary account',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => '1000.00',
        'current_balance' => '1000.00',
        'is_manual' => true,
        'is_active' => true,
    ]);

    $secondAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Secondary account',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => '500.00',
        'current_balance' => '500.00',
        'is_manual' => true,
        'is_active' => true,
    ]);

    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Family',
        'type' => 'household',
        'color' => '#000000',
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'slug' => 'rent-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $merchant = Merchant::query()->create([
        'user_id' => $user->id,
        'name' => 'Landlord',
        'normalized_name' => 'landlord',
        'is_active' => true,
    ]);

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Home',
        'slug' => 'home-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);

    return compact('user', 'account', 'secondAccount', 'scope', 'category', 'merchant', 'trackedItem');
}

function recurringManagementPayload(array $context, array $overrides = []): array
{
    return [
        'title' => 'Rent recurring entry',
        'account_id' => $context['account']->id,
        'scope_id' => $context['scope']->id,
        'category_id' => $context['category']->id,
        'tracked_item_id' => $context['trackedItem']->id,
        'merchant_id' => $context['merchant']->id,
        'description' => 'Recurring rent payment',
        'notes' => 'Recurring management notes',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 15],
        'start_date' => '2026-01-15',
        'end_date' => null,
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'occurrences_limit' => null,
        'expected_amount' => 50,
        'total_amount' => null,
        'installments_count' => null,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        ...$overrides,
    ];
}

function createManagedRecurringEntry(array $context, array $overrides = []): RecurringEntry
{
    return app(RecurringEntryManagementService::class)->store(
        $context['user'],
        recurringManagementPayload($context, $overrides)
    );
}

function shareRecurringAccount(
    Account $account,
    User $user,
    AccountMembershipRoleEnum $role,
    AccountMembershipStatusEnum $status = AccountMembershipStatusEnum::ACTIVE,
): AccountMembership {
    return AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'household_id' => null,
        'role' => $role->value,
        'status' => $status->value,
        'permissions' => null,
        'granted_by_user_id' => $account->user_id,
        'source' => MembershipSourceEnum::DIRECT->value,
        'joined_at' => now(),
    ]);
}
