<?php

namespace App\Services\Admin;

use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminInvitationService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function invite(User $admin, User $target): AdminInvitation
    {
        return DB::transaction(function () use ($admin, $target): AdminInvitation {
            $this->lockAdministratorRole();
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            abort_unless($admin->fresh()?->hasRole('admin') === true, 403);
            if ($target->hasRole('admin') || ! $target->hasVerifiedEmail() || $target->status !== 'active') {
                throw ValidationException::withMessages(['invitation' => __('admin_invitation.ineligible')]);
            }
            $recent = AdminInvitation::query()->where('target_user_id', $target->id);
            if ((clone $recent)->where('created_at', '>', now()->subMinute())->exists()
                || (clone $recent)->where('created_at', '>', now()->subHour())->count() >= 5) {
                abort(429, __('admin_invitation.rate_limited'));
            }
            $previous = AdminInvitation::query()->where('active_target_user_id', $target->id)->lockForUpdate()->first();
            if ($previous) {
                $this->close($previous, $admin, 'replaced');
            }
            $otp = (string) random_int(100000, 999999);
            $invitation = AdminInvitation::query()->create([
                'target_user_id' => $target->id,
                'active_target_user_id' => $target->id,
                'invited_by_user_id' => $admin->id,
                'email_hash' => hash('sha256', $target->email),
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(15),
            ]);
            $this->audit($invitation, $admin, 'created');
            Mail::to($target->email)->locale($target->preferredLocale())
                ->queue((new AdminInvitationMail($invitation->uuid, $otp))->afterCommit());

            return $invitation;
        }, 3);
    }

    public function cancel(User $admin, AdminInvitation $invitation): void
    {
        DB::transaction(function () use ($admin, $invitation): void {
            $this->lockAdministratorRole();
            User::query()->lockForUpdate()->findOrFail($invitation->target_user_id);
            abort_unless($admin->fresh()?->hasRole('admin') === true, 403);
            $invitation = AdminInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($invitation->active_target_user_id !== null) {
                $this->close($invitation, $admin, 'cancelled');
            }
        }, 3);
    }

    public function accept(User $target, AdminInvitation $invitation, string $otp): void
    {
        $accepted = DB::transaction(function () use ($target, $invitation, $otp): bool {
            $this->lockAdministratorRole();
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $invitation = AdminInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($invitation->target_user_id === $target->id, 404);
            $inviter = $invitation->invitedBy;
            if (! $invitation->isPending() || ! $target->hasVerifiedEmail() || $target->status !== 'active'
                || ! hash_equals($invitation->email_hash, hash('sha256', $target->email))
                || ! $inviter?->hasRole('admin') || ! $inviter?->hasVerifiedEmail() || $inviter?->status !== 'active' || $target->hasRole('admin')) {
                return false;
            }
            $invitation->increment('attempts');
            if (! Hash::check($otp, $invitation->otp_hash)) {
                if ($invitation->attempts >= 5) {
                    $this->close($invitation, $target, 'locked');
                }

                return false;
            }
            $target->assignRole('admin');
            $invitation->update(['otp_hash' => null, 'active_target_user_id' => null, 'accepted_at' => now()]);
            $this->audit($invitation, $target, 'accepted');

            return true;
        }, 3);
        if (! $accepted) {
            throw ValidationException::withMessages(['otp' => __('admin_invitation.invalid')]);
        }
    }

    private function lockAdministratorRole(): void
    {
        Role::query()->where('name', 'admin')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
    }

    private function close(AdminInvitation $invitation, User $actor, string $event): void
    {
        $invitation->update(['otp_hash' => null, 'active_target_user_id' => null, 'cancelled_at' => now()]);
        $this->audit($invitation, $actor, $event);
    }

    private function audit(AdminInvitation $invitation, User $actor, string $event): void
    {
        $this->auditLogService->adminInvitation($actor, $invitation, $event);
    }
}
