<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendPushBroadcastRequest;
use App\Http\Requests\Admin\SendPushOptInReminderRequest;
use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendTargetedPushBroadcastJob;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Notifications\AdminPushOptInReminderNotification;
use App\Services\Admin\AdminPushBroadcastPageService;
use App\Services\Admin\AdminTargetedPushBroadcastService;
use App\Services\Audit\AuditLogService;
use App\Services\Push\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PushBroadcastController extends Controller
{
    public function index(
        Request $request,
        AdminPushBroadcastPageService $pageService,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isAdmin(), 403);

        return Inertia::render('admin/PushBroadcasts', $pageService->pageData($request->only([
            'history_search',
            'history_type',
            'history_status',
            'history_date',
            'active_search',
            'inactive_search',
        ])));
    }

    public function store(
        SendPushBroadcastRequest $request,
        PushNotificationService $pushNotificationService,
        AdminTargetedPushBroadcastService $targetedPushBroadcastService,
        AuditLogService $auditLogService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $targetMode = (string) $request->validated('target_mode');

        if ($targetMode === 'single') {
            $targetUser = $targetedPushBroadcastService->findEligibleUserByUuid(
                (string) $request->validated('target_user_uuid'),
            );

            if (! $targetUser instanceof User) {
                throw ValidationException::withMessages([
                    'target_user_uuid' => __('admin.pushBroadcasts.form.errors.targetUserUnavailable'),
                ]);
            }

            $summary = $targetedPushBroadcastService->summarizeRecipients(collect([$targetUser]));
            $targetPayload = [
                'mode' => 'single',
                'user_uuids' => [$targetUser->uuid],
                'users' => [[
                    'uuid' => $targetUser->uuid,
                    'label' => trim(implode(' ', array_filter([$targetUser->name, $targetUser->surname]))) ?: $targetUser->email,
                    'email' => $targetUser->email,
                ]],
            ];
        } else {
            $summary = $pushNotificationService->eligibleAudienceSummary();
            $targetPayload = [
                'mode' => 'all',
                'user_uuids' => [],
                'users' => [],
            ];
        }

        $broadcast = PushBroadcast::query()->create([
            'created_by' => $user->id,
            'status' => 'queued',
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'url' => $request->validated('url'),
            'eligible_users_count' => $summary['eligible_users_count'],
            'target_tokens_count' => $summary['target_tokens_count'],
            'payload_snapshot' => [
                'title' => $request->validated('title'),
                'body' => $request->validated('body'),
                'url' => $request->validated('url'),
                'target' => $targetPayload,
            ],
            'queued_at' => now(),
        ]);

        $auditLogService->pushBroadcastQueued($user, $broadcast, $summary);

        if ($targetMode === 'single') {
            SendTargetedPushBroadcastJob::dispatch($broadcast->id);
        } else {
            SendPushNotificationJob::dispatch($broadcast->id);
        }

        return back()->with('success', __('admin.pushBroadcasts.flash.queued'));
    }

    public function remind(
        SendPushOptInReminderRequest $request,
        AdminTargetedPushBroadcastService $targetedPushBroadcastService,
    ): RedirectResponse {
        $user = User::query()
            ->with('settings')
            ->where('uuid', (string) $request->validated('user_uuid'))
            ->firstOrFail();

        if ($targetedPushBroadcastService->summarizeRecipients(collect([$user]))['eligible_users_count'] > 0) {
            throw ValidationException::withMessages([
                'user_uuid' => __('admin.pushBroadcasts.flash.reminderNotNeeded'),
            ]);
        }

        $user->notify(new AdminPushOptInReminderNotification);

        return back()->with('success', __('admin.pushBroadcasts.flash.reminderSent', [
            'user' => trim(implode(' ', array_filter([$user->name, $user->surname]))) ?: $user->email,
        ]));
    }
}
