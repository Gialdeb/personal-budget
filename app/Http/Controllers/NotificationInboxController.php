<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationInboxItemResource;
use App\Models\User;
use App\Services\Communication\UserNotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationInboxController extends Controller
{
    public function __construct(
        protected UserNotificationInboxService $inboxService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationInboxItemResource::collection($this->inboxService->paginate($user, 20)),
            'summary' => [
                'unread_count' => $this->inboxService->unreadCount($user),
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->previewPayload($user));
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->inboxService->markAsRead($user, $notification), 404);

        return response()->json($this->previewPayload($user));
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $this->inboxService->markAllAsRead($user);

        return response()->json([
            ...$this->previewPayload($user),
            'marked_count' => $count,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function previewPayload(User $user): array
    {
        return [
            'unread_count' => $this->inboxService->unreadCount($user),
            'latest' => NotificationInboxItemResource::collection($this->inboxService->latest($user, 6))->resolve(),
        ];
    }
}
