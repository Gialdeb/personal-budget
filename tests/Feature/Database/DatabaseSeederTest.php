<?php

use App\Models\Account;
use App\Models\Bank;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\NotificationTopic;
use App\Models\RecurringEntry;
use App\Models\ScheduledEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a clean quasi production base dataset', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()
        ->with(['settings', 'roles'])
        ->where('email', 'admin@admin.it')
        ->first();

    expect($admin)
        ->not()->toBeNull()
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->hasRole('user'))->toBeTrue()
        ->and($admin->settings?->active_year)->toBe(now()->year);

    expect(UserYear::query()->where('user_id', $admin->id)->pluck('year')->all())
        ->toBe([now()->year]);

    expect(Bank::query()->count())->toBeGreaterThan(0);

    expect(Account::query()
        ->where('user_id', $admin->id)
        ->pluck('name')
        ->all())
        ->toBe(['Cassa contanti']);

    $foundationCategories = Category::query()
        ->where('user_id', $admin->id)
        ->whereNull('account_id')
        ->whereNull('parent_id')
        ->orderBy('id')
        ->get(['name', 'direction_type', 'group_type']);

    expect($foundationCategories->pluck('name')->all())
        ->toBe(['Entrate', 'Spese', 'Bollette', 'Debiti', 'Risparmi']);

    expect($foundationCategories->keyBy('name')->map(fn (Category $category): array => [
        'direction_type' => $category->direction_type->value,
        'group_type' => $category->group_type->value,
    ])->all())->toBe([
        'Entrate' => ['direction_type' => 'income', 'group_type' => 'income'],
        'Spese' => ['direction_type' => 'expense', 'group_type' => 'expense'],
        'Bollette' => ['direction_type' => 'expense', 'group_type' => 'bill'],
        'Debiti' => ['direction_type' => 'expense', 'group_type' => 'debt'],
        'Risparmi' => ['direction_type' => 'expense', 'group_type' => 'saving'],
    ]);

    expect(Transaction::query()->count())->toBe(0)
        ->and(Budget::query()->count())->toBe(0)
        ->and(RecurringEntry::query()->count())->toBe(0)
        ->and(ScheduledEntry::query()->count())->toBe(0)
        ->and(TrackedItem::query()->count())->toBe(0);

    expect(NotificationTopic::query()->whereIn('key', [
        'automation_failed',
        'import_completed',
        'monthly_report_ready',
        'auth_verify_email',
        'auth_reset_password',
    ])->count())->toBe(5)
        ->and(CommunicationTemplate::query()->whereIn('key', [
            'account_invitation_mail',
            'auth_verify_email_mail',
            'auth_reset_password_mail',
            'welcome_after_verification_mail',
            'welcome_after_verification_database',
            'import_completed_mail',
            'import_completed_database',
        ])->count())->toBe(7)
        ->and(CommunicationCategory::query()->whereIn('key', [
            'auth.verify_email',
            'auth.reset_password',
            'user.welcome_after_verification',
            'imports.completed',
            'reports.weekly_ready',
            'sharing.account_invitation',
        ])->count())->toBe(6);
});

it('keeps demo data out of the base seeder', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Account::query()->count())->toBe(1)
        ->and(Category::query()->whereNotNull('account_id')->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(TrackedItem::query()->count())->toBe(0);
});
