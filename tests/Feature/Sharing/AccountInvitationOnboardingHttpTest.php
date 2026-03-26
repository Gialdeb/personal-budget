<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\InvitationStatusEnum;
use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\OutboundMessage;
use App\Models\User;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('returns registration_required for a valid invitation when no user exists for the email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'registration-required-token-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'registration_required')
        ->assertJsonPath('data.email', 'newuser@gmail.com')
        ->assertJsonPath('data.requires_registration', true)
        ->assertJsonPath('data.requires_login', false)
        ->assertJsonPath('data.can_accept', false);
});

it('renders a user-facing registration page for a valid invitation when no user exists for the email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $plainToken = 'registration-required-page-token-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccountInvitation')
            ->where('invitation.state', 'registration_required')
            ->where('invitation.email', 'newuser@gmail.com')
            ->where('invitation.account.name', 'Conto Famiglia')
            ->where('invitation.role_label', 'Può modificare')
            ->where('token', $plainToken));
});

it('returns login_required for a valid invitation when user exists but is not authenticated', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'login-required-token-login-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'login_required')
        ->assertJsonPath('data.requires_registration', false)
        ->assertJsonPath('data.requires_login', true)
        ->assertJsonPath('data.can_accept', false);
});

it('renders a user-facing login page for a valid invitation when user exists but is not authenticated', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $plainToken = 'login-required-page-token-login-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $url = route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken;

    $this->get($url)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccountInvitation')
            ->where('invitation.state', 'login_required')
            ->where('invitation.email', 'wife@gmail.com')
            ->where('token', $plainToken));

    expect(session('url.intended'))->toBe($url);
});

it('returns ready_to_accept for a valid invitation when authenticated user email matches', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'ready-to-accept-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'ready_to_accept')
        ->assertJsonPath('data.requires_registration', false)
        ->assertJsonPath('data.requires_login', false)
        ->assertJsonPath('data.can_accept', true);
});

it('renders a user-facing confirmation page when the authenticated user email matches the invitation', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $plainToken = 'ready-to-accept-page-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->get(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccountInvitation')
            ->where('invitation.state', 'ready_to_accept')
            ->where('invitation.can_accept', true)
            ->where('invitation.account.name', 'Conto Famiglia'));
});
it('registers a new user from a valid account invitation', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'register-from-invitation-token-123';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->withSession(['locale' => 'it'])->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'newuser@gmail.com')
        ->assertJsonPath('data.membership.status', 'active')
        ->assertJsonPath('data.membership.role', 'editor');

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'newuser@gmail.com')->first();
    $membership = AccountMembership::query()->where('user_id', $user?->id)->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('user'))->toBeTrue()
        ->and($user->base_currency_code)->toBe('EUR')
        ->and($user->format_locale)->toBe('it-IT')
        ->and($user->settings?->active_year)->toBe((int) now()->year)
        ->and($user->email_verified_at)->not->toBeNull();
    expect(Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cassa contanti')
        ->exists())->toBeTrue();
    expect($membership)->not->toBeNull()
        ->and($membership->status->value)->toBe('active')
        ->and($membership->role->value)->toBe('editor');

    expect($invitation->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});

it('does not dispatch the welcome-after-verification communication when registering from an account invitation', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'register-no-welcome-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $user = User::query()->where('email', 'newuser@gmail.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and(OutboundMessage::query()
            ->whereHas('category', fn ($query) => $query->where('key', 'user.welcome_after_verification'))
            ->where('recipient_type', $user->getMorphClass())
            ->where('recipient_id', $user->getKey())
            ->exists())->toBeFalse();
});

it('renders a user-facing mismatch page when authenticated with a different email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $wrongUser = User::factory()->create(['email' => 'other@gmail.com']);
    User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $plainToken = 'mismatch-page-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($wrongUser)
        ->get(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccountInvitation')
            ->where('invitation.state', 'email_mismatch')
            ->where('invitation.email', 'wife@gmail.com'));
});

it('newly registered invited users can see the shared account in accessible dashboard filters', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $plainToken = 'register-dashboard-shared-account-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'household_id' => $sharedAccount->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $user = User::query()->where('email', 'newuser@gmail.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2026, 'month' => 3]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.filters.account_options', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['value'] === $sharedAccount->uuid
                    && $option['label'] === 'Conto Famiglia'
                    && $option['is_shared'] === true
                    && $option['is_owned'] === false
                    && $option['membership_role'] === AccountMembershipRoleEnum::VIEWER->value
                    && $option['membership_status'] === 'active')));
});

it('redirects invited registrations to accounts settings where the shared account is visible', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $plainToken = 'register-settings-shared-account-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'household_id' => $sharedAccount->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->withSession(['locale' => 'it'])
        ->post(route('account-invitations.register', $invitation), [
            'token' => $plainToken,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect(route('accounts.edit'));

    $this->assertAuthenticated();

    $this->get(route('accounts.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('accounts.data', fn ($accounts) => collect($accounts)
                ->every(fn ($account) => $account['uuid'] !== $sharedAccount->uuid))
            ->where('shared_accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid
                    && $account['name'] === 'Conto Famiglia'
                    && $account['membership_role'] === AccountMembershipRoleEnum::VIEWER->value
                    && $account['membership_status'] === 'active')));
});

it('registers an invited user on the invited account without confusing it with the default cash account', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Risorsa']);

    $plainToken = 'register-risorsa-vs-cassa-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'household_id' => $sharedAccount->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $user = User::query()->where('email', 'newuser@gmail.com')->firstOrFail();
    $membership = AccountMembership::query()
        ->where('user_id', $user->id)
        ->where('account_id', $sharedAccount->id)
        ->firstOrFail();

    expect($invitation->account_id)->toBe($sharedAccount->id)
        ->and($membership->account_id)->toBe($sharedAccount->id)
        ->and(Account::query()
            ->where('user_id', $user->id)
            ->where('name', 'Cassa contanti')
            ->exists())->toBeTrue()
        ->and(Account::query()
            ->where('user_id', $user->id)
            ->where('name', 'Risorsa')
            ->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('accounts.data', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['name'] === 'Cassa contanti')
                && ! collect($accounts)->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid))
            ->where('shared_accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid
                    && $account['name'] === 'Risorsa'
                    && $account['membership_role'] === AccountMembershipRoleEnum::EDITOR->value
                    && $account['membership_status'] === 'active')));
});

it('shows the accepted invited user in the owner account members dataset', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Risorsa']);

    $plainToken = 'owner-members-dataset-token-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'household_id' => $sharedAccount->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $this->actingAs($owner)
        ->getJson(route('sharing.accounts.members', $sharedAccount))
        ->assertOk()
        ->assertJsonPath('data.0.user.email', 'newuser@gmail.com')
        ->assertJsonPath('data.0.role', AccountMembershipRoleEnum::VIEWER->value)
        ->assertJsonPath('data.0.status', 'active');
});

it('redirects authenticated invitation acceptance to accounts settings where the invited account is visible as shared', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'invitee@gmail.com']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Risorsa']);
    $personalAccount = createTestAccount($invitee, ['name' => 'Conto personale invitato']);

    $plainToken = 'accept-authenticated-settings-risorsa-123456789';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'household_id' => $sharedAccount->household_id,
        'email' => 'invitee@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->post(route('account-invitations.accept-authenticated', $invitation), [
            'token' => $plainToken,
        ])
        ->assertRedirect(route('accounts.edit'));

    $this->actingAs($invitee)
        ->get(route('accounts.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('accounts.data', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $personalAccount->uuid
                    && $account['name'] === 'Conto personale invitato')
                && ! collect($accounts)->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid))
            ->where('shared_accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid
                    && $account['name'] === 'Risorsa'
                    && $account['membership_role'] === AccountMembershipRoleEnum::VIEWER->value
                    && $account['membership_status'] === 'active')));
});

it('accepts a valid account invitation for an authenticated existing user', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'accept-authenticated-token-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->postJson(route('account-invitations.accept-authenticated', $invitation), [
            'token' => $plainToken,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.user.email', 'wife@gmail.com');

    expect($invitation->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});
