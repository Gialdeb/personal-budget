<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendPushBroadcastRequest;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Push\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PushBroadcastController extends Controller
{
    public function index(Request $request, PushNotificationService $pushNotificationService): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isAdmin(), 403);

        $broadcasts = PushBroadcast::query()
            ->with('creator')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/PushBroadcasts', [
            'audience' => $pushNotificationService->eligibleAudienceSummary(),
            'broadcasts' => $broadcasts->through(fn (PushBroadcast $broadcast): array => [
                'uuid' => $broadcast->uuid,
                'status' => $broadcast->status,
                'title' => $broadcast->title,
                'body' => $broadcast->body,
                'url' => $broadcast->url,
                'eligible_users_count' => $broadcast->eligible_users_count,
                'target_tokens_count' => $broadcast->target_tokens_count,
                'sent_count' => $broadcast->sent_count,
                'failed_count' => $broadcast->failed_count,
                'invalidated_count' => $broadcast->invalidated_count,
                'queued_at' => $broadcast->queued_at?->toJSON(),
                'started_at' => $broadcast->started_at?->toJSON(),
                'finished_at' => $broadcast->finished_at?->toJSON(),
                'error_message' => $broadcast->error_message,
                'creator' => $broadcast->creator === null ? null : [
                    'uuid' => $broadcast->creator->uuid,
                    'name' => trim(implode(' ', array_filter([
                        $broadcast->creator->name,
                        $broadcast->creator->surname,
                    ]))),
                ],
            ]),
        ]);
    }

    public function store(
        SendPushBroadcastRequest $request,
        PushNotificationService $pushNotificationService,
        AuditLogService $auditLogService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $summary = $pushNotificationService->eligibleAudienceSummary();

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
            ],
            'queued_at' => now(),
        ]);

        $auditLogService->pushBroadcastQueued($user, $broadcast, $summary);

        SendPushNotificationJob::dispatch($broadcast->id);

        return back()->with('success', __('admin.pushBroadcasts.flash.queued'));
    }
}
