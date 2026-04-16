<?php

namespace App\Services\Admin;

use App\Models\PushBroadcast;
use App\Models\User;
use App\Services\Push\DeviceTokenService;
use App\Services\Push\PushNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AdminPushBroadcastPageService
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
        protected DeviceTokenService $deviceTokenService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function pageData(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'audience' => [
                ...$this->pushNotificationService->eligibleAudienceSummary(),
                'users_with_active_tokens_count' => $this->usersWithActiveTokensCount(),
                'users_without_active_push_count' => $this->usersWithoutActivePushCount(),
            ],
            'filters' => $normalizedFilters,
            'options' => $this->filterOptions(),
            'broadcasts' => $this->paginateBroadcasts($normalizedFilters),
            'activePushUsers' => $this->paginateActivePushUsers($normalizedFilters),
            'inactivePushUsers' => $this->paginateInactivePushUsers($normalizedFilters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function normalizeFilters(array $filters = []): array
    {
        return [
            'history_search' => trim((string) ($filters['history_search'] ?? '')),
            'history_type' => trim((string) ($filters['history_type'] ?? 'all')) ?: 'all',
            'history_status' => trim((string) ($filters['history_status'] ?? 'all')) ?: 'all',
            'history_date' => trim((string) ($filters['history_date'] ?? '')),
            'active_search' => trim((string) ($filters['active_search'] ?? '')),
            'inactive_search' => trim((string) ($filters['inactive_search'] ?? '')),
        ];
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public function filterOptions(): array
    {
        return [
            'history_types' => [
                ['value' => 'all', 'label' => __('admin.pushBroadcasts.filters.types.all')],
                ['value' => 'broadcast', 'label' => __('admin.pushBroadcasts.filters.types.broadcast')],
                ['value' => 'single_user', 'label' => __('admin.pushBroadcasts.filters.types.single_user')],
            ],
            'history_statuses' => [
                ['value' => 'all', 'label' => __('admin.pushBroadcasts.filters.statuses.all')],
                ['value' => 'queued', 'label' => __('admin.pushBroadcasts.statuses.queued')],
                ['value' => 'sending', 'label' => __('admin.pushBroadcasts.statuses.sending')],
                ['value' => 'completed', 'label' => __('admin.pushBroadcasts.statuses.completed')],
                ['value' => 'completed_with_failures', 'label' => __('admin.pushBroadcasts.statuses.completed_with_failures')],
                ['value' => 'failed', 'label' => __('admin.pushBroadcasts.statuses.failed')],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function paginateBroadcasts(array $filters, int $perPage = 15): array
    {
        $paginator = PushBroadcast::query()
            ->with('creator')
            ->when($filters['history_search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['history_search'];

                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%");
                });
            })
            ->when($filters['history_type'] !== 'all', function (Builder $query) use ($filters): void {
                if ($filters['history_type'] === 'single_user') {
                    $query->where('payload_snapshot->target->mode', 'single');

                    return;
                }

                $query->where(function (Builder $nestedQuery): void {
                    $nestedQuery
                        ->where('payload_snapshot->target->mode', 'all')
                        ->orWhereNull('payload_snapshot->target->mode');
                });
            })
            ->when(
                $filters['history_status'] !== 'all',
                fn (Builder $query): Builder => $query->where('status', $filters['history_status']),
            )
            ->when(
                $filters['history_date'] !== '',
                fn (Builder $query): Builder => $query->whereDate('created_at', $filters['history_date']),
            )
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'history_page')
            ->through(fn (PushBroadcast $broadcast): array => $this->mapBroadcast($broadcast))
            ->withQueryString();

        return $this->resourcePaginator($paginator);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function paginateActivePushUsers(array $filters, int $perPage = 10): array
    {
        $paginator = User::query()
            ->with('settings')
            ->withCount([
                'deviceTokens as active_token_rows_count' => fn (Builder $query): Builder => $query->active(),
            ])
            ->withMax([
                'deviceTokens as latest_active_token_seen_at' => fn (Builder $query): Builder => $query->active(),
            ], 'last_seen_at')
            ->whereHas('deviceTokens', fn (Builder $query): Builder => $query->active())
            ->when($filters['active_search'] !== '', function (Builder $query) use ($filters): void {
                $this->applyUserSearch($query, $filters['active_search']);
            })
            ->orderByDesc('latest_active_token_seen_at')
            ->orderBy('email')
            ->paginate($perPage, ['*'], 'active_page')
            ->through(fn (User $user): array => $this->mapActivePushUser($user))
            ->withQueryString();

        return $this->resourcePaginator($paginator);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function paginateInactivePushUsers(array $filters, int $perPage = 10): array
    {
        $paginator = User::query()
            ->with('settings')
            ->withCount([
                'deviceTokens as active_token_rows_count' => fn (Builder $query): Builder => $query->active(),
            ])
            ->withMax([
                'deviceTokens as latest_active_token_seen_at' => fn (Builder $query): Builder => $query->active(),
            ], 'last_seen_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('deviceTokens', fn (Builder $tokenQuery): Builder => $tokenQuery->active())
                    ->orWhereHas('settings', fn (Builder $settingsQuery): Builder => $settingsQuery->where('settings->notifications->push->enabled', false));
            })
            ->when($filters['inactive_search'] !== '', function (Builder $query) use ($filters): void {
                $this->applyUserSearch($query, $filters['inactive_search']);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'inactive_page')
            ->through(fn (User $user): array => $this->mapInactivePushUser($user))
            ->withQueryString();

        return $this->resourcePaginator($paginator);
    }

    protected function usersWithActiveTokensCount(): int
    {
        return User::query()
            ->whereHas('deviceTokens', fn (Builder $query): Builder => $query->active())
            ->count();
    }

    protected function usersWithoutActivePushCount(): int
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('deviceTokens', fn (Builder $tokenQuery): Builder => $tokenQuery->active())
                    ->orWhereHas('settings', fn (Builder $settingsQuery): Builder => $settingsQuery->where('settings->notifications->push->enabled', false));
            })
            ->count();
    }

    protected function applyUserSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $nestedQuery) use ($search): void {
            $nestedQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('surname', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapBroadcast(PushBroadcast $broadcast): array
    {
        $targetMode = data_get($broadcast->payload_snapshot, 'target.mode') === 'single'
            ? 'single_user'
            : 'broadcast';
        $targetUser = data_get($broadcast->payload_snapshot, 'target.users.0');
        $creatorName = trim(implode(' ', array_filter([
            $broadcast->creator?->name,
            $broadcast->creator?->surname,
        ])));

        return [
            'uuid' => $broadcast->uuid,
            'status' => $broadcast->status,
            'title' => $broadcast->title,
            'body_snippet' => Str::limit($broadcast->body, 110),
            'url' => $broadcast->url,
            'target_mode' => $targetMode,
            'target_label' => $targetMode === 'single_user'
                ? trim((string) data_get($targetUser, 'label', data_get($targetUser, 'email', __('admin.pushBroadcasts.targets.unknownUser'))))
                : __('admin.pushBroadcasts.targets.allEligibleUsers'),
            'target_users_count' => $targetMode === 'single_user' ? 1 : $broadcast->eligible_users_count,
            'eligible_users_count' => $broadcast->eligible_users_count,
            'target_tokens_count' => $broadcast->target_tokens_count,
            'sent_count' => $broadcast->sent_count,
            'failed_count' => $broadcast->failed_count,
            'invalidated_count' => $broadcast->invalidated_count,
            'queued_at' => ($broadcast->queued_at ?? $broadcast->created_at)?->toIso8601String(),
            'finished_at' => $broadcast->finished_at?->toIso8601String(),
            'error_message' => $broadcast->error_message,
            'creator' => $broadcast->creator === null ? null : [
                'uuid' => $broadcast->creator->uuid,
                'name' => $creatorName !== '' ? $creatorName : $broadcast->creator->email,
                'email' => $broadcast->creator->email,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapActivePushUser(User $user): array
    {
        $deviceCount = $this->deviceTokenService->activeBroadcastTokensForUser($user)->count();
        $status = $user->pushNotificationsEnabled() ? 'eligible' : 'disabled_in_preferences';

        return [
            'uuid' => $user->uuid,
            'name' => trim(implode(' ', array_filter([$user->name, $user->surname]))) ?: $user->email,
            'email' => $user->email,
            'active_devices_count' => $deviceCount,
            'last_seen_at' => $this->normalizeDateTime($user->latest_active_token_seen_at),
            'eligibility_status' => $status,
            'can_target_push' => $status === 'eligible' && $deviceCount > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapInactivePushUser(User $user): array
    {
        $status = $user->pushNotificationsEnabled()
            ? 'no_active_devices'
            : 'disabled_in_preferences';

        return [
            'uuid' => $user->uuid,
            'name' => trim(implode(' ', array_filter([$user->name, $user->surname]))) ?: $user->email,
            'email' => $user->email,
            'active_devices_count' => (int) ($user->active_token_rows_count ?? 0),
            'last_seen_at' => $this->normalizeDateTime($user->latest_active_token_seen_at),
            'status' => $status,
        ];
    }

    protected function normalizeDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @return array{
     *     data: array<int, mixed>,
     *     links: array{first: ?string, last: ?string, prev: ?string, next: ?string},
     *     meta: array{
     *         current_page: int,
     *         from: ?int,
     *         last_page: int,
     *         links: array<int, array{url: ?string, label: string, active: bool}>,
     *         path: string,
     *         per_page: int,
     *         to: ?int,
     *         total: int
     *     }
     * }
     */
    protected function resourcePaginator(LengthAwarePaginator $paginator): array
    {
        /** @var array<int, array{url: ?string, label: string, active: bool}> $paginationLinks */
        $paginationLinks = $paginator->linkCollection()
            ->map(fn (array $link): array => [
                'url' => $link['url'],
                'label' => (string) $link['label'],
                'active' => (bool) $link['active'],
            ])
            ->values()
            ->all();

        return [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginationLinks,
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
