<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Exceptions\CannotInviteToAccountException;
use App\Exceptions\InvalidAccountInvitationException;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountInvitationService;
use App\Services\Sharing\AccountMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows original owner to create an account invitation', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $service = app(AccountInvitationService::class);

    $result = $service->createInvitation(
        $account,
        $owner,
        'wife@gmail.com',
        AccountMembershipRoleEnum::VIEWER->value,
        null,
        now()->addDays(7),
    );

    expect($result['invitation'])->toBeInstanceOf(AccountInvitation::class)
        ->and($result['invitation']->status)->toBe(InvitationStatusEnum::PENDING)
        ->and($result['plain_token'])->not->toBeEmpty();
});

it('prevents non original owner from inviting to the same account', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $other = User::factory()->create(['email' => 'other@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $service = app(AccountInvitationService::class);

    expect(fn () => $service->createInvitation(
        $account,
        $other,
        'wife@gmail.com',
        AccountMembershipRoleEnum::VIEWER->value,
    ))->toThrow(CannotInviteToAccountException::class);
});

it('accepts an account invitation and creates membership', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        $invitee->email,
        AccountMembershipRoleEnum::EDITOR->value,
        null,
        now()->addDays(7),
    );

    $membership = $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    );

    expect($membership)->toBeInstanceOf(AccountMembership::class)
        ->and($membership->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($membership->role)->toBe(AccountMembershipRoleEnum::EDITOR);

    expect($created['invitation']->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});

it('rejects invitation acceptance if email does not match', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wrong@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        'wife@gmail.com',
        AccountMembershipRoleEnum::VIEWER->value,
        null,
        now()->addDays(7),
    );

    expect(fn () => $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    ))->toThrow(InvalidAccountInvitationException::class);
});
