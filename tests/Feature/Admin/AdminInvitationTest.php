<?php

use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->target = User::factory()->create();
});

function issueAdminInvitation($test): array
{
    $test->actingAs($test->admin)->post(route('admin.users.admin-invitations.store', $test->target))->assertRedirect()->assertSessionHas('success');
    $invitation = AdminInvitation::query()->latest('id')->firstOrFail();
    $mail = Mail::queued(AdminInvitationMail::class)->last();

    return [$invitation, $mail->otp];
}

test('only admins can invite or cancel and staff has no implied privileges', function (string $role) {
    $actor = User::factory()->create();
    $actor->syncRoles([$role]);
    $invitation = AdminInvitation::factory()->create(['target_user_id' => $this->target->id]);
    $this->actingAs($actor)->post(route('admin.users.admin-invitations.store', $this->target))->assertForbidden();
    $this->delete(route('admin.admin-invitations.destroy', $invitation))->assertForbidden();
    Mail::assertNothingQueued();
})->with(['user', 'staff']);

test('invitation queues encrypted localized email and grants no privileges before confirmation', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    expect($this->target->fresh()->hasRole('admin'))->toBeFalse()
        ->and(Hash::check($otp, $invitation->otp_hash))->toBeTrue()
        ->and($invitation->otp_hash)->not->toBe($otp)
        ->and($invitation->toArray())->not->toHaveKeys(['otp_hash', 'email_hash']);
    Mail::assertQueued(AdminInvitationMail::class, fn ($mail) => $mail->hasTo($this->target->email) && $mail->locale === 'it' && (new SendQueuedMailable($mail))->shouldBeEncrypted);
    $mail = Mail::queued(AdminInvitationMail::class)->first();
    expect($mail->render())->toContain($otp, route('admin-invitations.show', $invitation), '15 minuti');
    $this->actingAs($this->target)->get(route('admin.index'))->assertForbidden();
});

test('target alone can confirm and consume code with audit and session rotation', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    $this->actingAs($this->target)->withSession(['test_identity' => 'target']);
    $oldSessionId = session()->getId();
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertRedirect(route('admin.users'))->assertSessionHas('success');
    expect($this->target->fresh()->hasRole('admin'))->toBeTrue()
        ->and($invitation->fresh()->otp_hash)->toBeNull()
        ->and($invitation->fresh()->active_target_user_id)->toBeNull()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and(session()->getId())->not->toBe($oldSessionId);
    $this->actingAs($this->target->fresh())->get(route('admin.index'))->assertSuccessful();
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertSessionHasErrors('otp');
    expect(Activity::where('description', 'admin.invitation.accepted')->count())->toBe(1);
    $activity = Activity::where('description', 'admin.invitation.created')->firstOrFail();
    expect($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->properties['target_user_id'])->toBe($this->target->id)
        ->and($activity->properties->toJson())->not->toContain($otp);
});

test('foreign account cannot view or confirm invitation even knowing code', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('admin-invitations.show', $invitation))->assertNotFound();
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertNotFound();
    expect($this->target->fresh()->hasRole('admin'))->toBeFalse()->and($invitation->fresh()->attempts)->toBe(0);
});

test('confirmation requires authenticated verified account', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    auth()->logout();
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertRedirect(route('login'));
    $this->target->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->target)->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertRedirect(route('verification.notice'));
});

test('unverified inactive and already admin accounts cannot be invited', function (string $state) {
    if ($state === 'unverified') {
        $this->target->forceFill(['email_verified_at' => null])->save();
    } elseif ($state === 'admin') {
        $this->target->assignRole('admin');
    } else {
        $this->target->forceFill(['status' => $state])->save();
    }
    $this->actingAs($this->admin)->post(route('admin.users.admin-invitations.store', $this->target))->assertSessionHasErrors('invitation');
    expect(AdminInvitation::count())->toBe(0);
    Mail::assertNothingQueued();
})->with(['unverified', 'admin', 'banned', 'suspended']);

test('wrong codes persist attempts and fifth failure irreversibly invalidates code', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    $wrong = $otp === '000000' ? '000001' : '000000';
    $this->actingAs($this->target);
    for ($i = 1; $i <= 5; $i++) {
        $this->post(route('admin-invitations.accept', $invitation), ['otp' => $wrong])->assertSessionHasErrors('otp');
        expect($invitation->fresh()->attempts)->toBe($i);
    }
    $this->travel(61)->seconds();
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertSessionHasErrors('otp');
    expect($invitation->fresh()->otp_hash)->toBeNull()->and($this->target->fresh()->hasRole('admin'))->toBeFalse();
});

test('expired code changed email or revoked inviter cannot grant admin', function (string $state) {
    [$invitation, $otp] = issueAdminInvitation($this);
    if ($state === 'expired') {
        $this->travel(16)->minutes();
    } elseif ($state === 'email') {
        $this->target->forceFill(['email' => 'changed@example.test'])->save();
    } else {
        $this->admin->removeRole('admin');
    }
    $this->actingAs($this->target)->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertSessionHasErrors('otp');
    expect($this->target->fresh()->hasRole('admin'))->toBeFalse();
})->with(['expired', 'email', 'inviter']);

test('resend replaces invitation and previous code can never be consumed', function () {
    [$old, $oldOtp] = issueAdminInvitation($this);
    $this->travel(61)->seconds();
    [$new, $newOtp] = issueAdminInvitation($this);
    expect($old->fresh()->cancelled_at)->not->toBeNull()->and($old->fresh()->otp_hash)->toBeNull()
        ->and(AdminInvitation::whereNotNull('active_target_user_id')->count())->toBe(1);
    $this->actingAs($this->target)->post(route('admin-invitations.accept', $old), ['otp' => $oldOtp])->assertSessionHasErrors('otp');
    $this->post(route('admin-invitations.accept', $new), ['otp' => $newOtp])->assertRedirect(route('admin.users'));
});

test('cancel records audit and permanently invalidates code', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    $this->actingAs($this->admin)->delete(route('admin.admin-invitations.destroy', $invitation))->assertRedirect()->assertSessionHas('success');
    $this->actingAs($this->target)->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertSessionHasErrors('otp');
    expect(Activity::where('description', 'admin.invitation.cancelled')->count())->toBe(1);
});

test('immediate duplicate requests are throttled without replacing active invitation', function () {
    [$invitation] = issueAdminInvitation($this);
    $this->actingAs($this->admin)->post(route('admin.users.admin-invitations.store', $this->target))->assertTooManyRequests();
    expect(AdminInvitation::count())->toBe(1)->and($invitation->fresh()->isPending())->toBeTrue();
});

test('recipient hourly limit survives replacement and route rate limit blocks brute force', function () {
    for ($i = 0; $i < 5; $i++) {
        [$invitation, $otp] = issueAdminInvitation($this);
        $this->travel(61)->seconds();
    }
    $this->actingAs($this->admin)->post(route('admin.users.admin-invitations.store', $this->target))->assertTooManyRequests();
    $this->actingAs($this->target);
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('admin-invitations.accept', $invitation), ['otp' => '000000'])->assertSessionHasErrors('otp');
    }
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertTooManyRequests();
});

test('database prevents two active invitations for the same target', function () {
    [$invitation] = issueAdminInvitation($this);
    expect(fn () => AdminInvitation::factory()->create(['target_user_id' => $this->target->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('last administrator cannot demote ban suspend or delete own account', function () {
    $this->actingAs($this->admin);
    $this->patch(route('admin.users.roles.update', $this->admin), ['roles' => ['user']])->assertSessionHasErrors('user');
    $this->patch(route('admin.users.ban', $this->admin), [])->assertSessionHasErrors('user');
    $this->patch(route('admin.users.suspend', $this->admin), [])->assertSessionHasErrors('user');
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('profile deletion remains possible with another administrator', function () {
    $this->target->assignRole('admin');
    $this->actingAs($this->admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect();
    expect(User::find($this->admin->id))->toBeNull()->and($this->target->fresh()->hasRole('admin'))->toBeTrue();
});

test('invitation page exposes no OTP or administrator audit details and IDOR is denied', function () {
    [$invitation] = issueAdminInvitation($this);
    $this->actingAs($this->target)->get(route('admin-invitations.show', $invitation))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/AdminInvitation')
            ->where('invitation.uuid', $invitation->uuid)
            ->where('invitation.pending', true)
            ->missing('invitation.otp_hash')
            ->missing('invitation.invited_by_user_id')
            ->where('auth.user.is_admin', false));
    $this->get(route('admin.activity-log'))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.users.admin-invitations.store', (string) Str::uuid()))->assertNotFound();
    $this->patch(route('admin.users.roles.update', $this->target), ['roles' => ['admin']])->assertSessionHasErrors('roles.0');
    expect($this->target->fresh()->hasRole('admin'))->toBeFalse();
});

test('an impersonated session cannot personally confirm the invitation', function () {
    [$invitation, $otp] = issueAdminInvitation($this);
    $this->actingAs($this->target)->withSession(['impersonated_by' => $this->admin->id]);
    $this->get(route('admin-invitations.show', $invitation))->assertNotFound();
    $this->post(route('admin-invitations.accept', $invitation), ['otp' => $otp])->assertForbidden();
    expect($this->target->fresh()->hasRole('admin'))->toBeFalse();
});

test('administrator invitation volume is limited across recipients', function () {
    $this->actingAs($this->admin);
    for ($i = 0; $i < 10; $i++) {
        $recipient = User::factory()->create();
        $this->post(route('admin.users.admin-invitations.store', $recipient))->assertRedirect();
    }
    $this->post(route('admin.users.admin-invitations.store', $this->target))->assertTooManyRequests();
    expect(AdminInvitation::count())->toBe(10);
});
