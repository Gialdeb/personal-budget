<?php

namespace App\Http\Middleware;

use App\Http\Resources\NotificationInboxItemResource;
use App\Models\Category;
use App\Models\ChangelogRelease;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Communication\UserNotificationInboxService;
use App\Services\Transactions\TransactionNavigationService;
use App\Support\ContextualHelp\CurrentContextualHelpResolver;
use App\Support\Pwa\PwaManifestData;
use App\Support\Seo\PublicPageSeoResolver;
use App\Supports\CategoryHierarchy;
use App\Supports\Currency\CurrencySupport;
use App\Supports\Locale\LocaleResolver;
use App\Supports\ManagementContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return app(PwaManifestData::class)->version();
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $localeResolver = app(LocaleResolver::class);

        $shared = [
            ...parent::share($request),
            'auth' => [
                'user' => $this->sharedAuthUser($request),
            ],
            'features' => [
                'imports_enabled' => (bool) config('features.imports.enabled'),
                'reports_enabled' => (bool) config('features.reports.enabled'),
                'report_sections' => [
                    'kpis_enabled' => (bool) config('features.reports.sections.kpis'),
                    'categories_enabled' => (bool) config('features.reports.sections.categories'),
                    'accounts_enabled' => (bool) config('features.reports.sections.accounts'),
                ],
                'push_notifications_enabled' => (bool) config('features.push_notifications.enabled'),
            ],
            'maintenanceState' => fn (): array => [
                'active' => app()->isDownForMaintenance(),
                'status' => app()->isDownForMaintenance() ? 'active' : 'inactive',
                'checked_at' => now()->toJSON(),
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
            'locale' => fn () => [
                'current' => $localeResolver->current($request),
                'fallback' => $localeResolver->fallback(),
                'available' => $localeResolver->available(),
                'currencies' => collect(app(CurrencySupport::class)->supported())
                    ->mapWithKeys(fn (array $currency, string $code): array => [
                        $code => [
                            'code' => $currency['code'],
                            'name' => $currency['name'],
                            'symbol' => $currency['symbol'],
                            'minor_unit' => $currency['minor_unit'],
                            'symbol_position' => $currency['symbol_position'],
                        ],
                    ])
                    ->all(),
            ],
        ];

        if ($this->usesAuthenticatedAppShell($request)) {
            $shared['app'] = fn (): array => [
                'name' => config('app.name'),
                'version' => config('app.version'),
                'environment' => config('app.env'),
                ...$this->resolveSharedChangelogMeta(),
            ];
            $shared['sidebarOpen'] = ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true';
            $shared['notificationInbox'] = fn (): ?array => $this->sharedNotificationInbox($request);
            $shared['sessionWarning'] = fn (): ?array => $this->sharedSessionWarning($request);
        }

        if ($this->shouldShareEntrySearch($request)) {
            $shared['entrySearch'] = fn (): ?array => $this->sharedEntrySearch($request);
        }

        if ($this->shouldShareContextualHelp($request)) {
            $shared['contextualHelp'] = fn (): ?array => $this->sharedContextualHelp($request);
        }

        if ($this->shouldShareTransactionsNavigation($request)) {
            $shared['transactionsNavigation'] = fn (): ?array => $this->resolveTransactionsNavigation($request);
        }

        if ($this->shouldShareSettingsNavigation($request)) {
            $shared['settingsNavigation'] = fn (): array => [
                'has_shared_categories' => $this->hasSharedCategories($request),
            ];
        }

        if ($this->isIndexablePublicRoute($request)) {
            $shared['publicSeo'] = fn (): ?array => $this->sharedPublicSeo($request);
            $shared['analytics'] = fn (): array => $this->sharedAnalytics($request);
        }

        if ($this->shouldSharePublicIntegrations($request)) {
            $shared['publicIntegrations'] = fn (): array => $this->sharedPublicIntegrations();
        }

        return $shared;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedAnalytics(Request $request): array
    {
        return [
            'current_route_name' => $request->route()?->getName(),
            'umami' => [
                'enabled' => (bool) config('analytics.umami.enabled'),
                'host_url' => config('analytics.umami.host_url'),
                'website_id' => config('analytics.umami.website_id'),
                'domains' => config('analytics.umami.domains', []),
                'environment_tag' => config('analytics.umami.environment_tag'),
                'respect_dnt' => (bool) config('analytics.umami.respect_dnt', true),
                'public_route_names' => config('analytics.umami.public_route_names', []),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedPublicIntegrations(): array
    {
        return [
            'tawkTo' => [
                'enabled' => (bool) config('services.tawk_to.enabled', false),
                'propertyId' => config('services.tawk_to.property_id'),
                'widgetId' => config('services.tawk_to.widget_id'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sharedAuthUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $user->loadMissing('settings');

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'locale' => $user->locale,
            'format_locale' => $user->format_locale,
            'number_thousands_separator' => $user->number_thousands_separator ?: '.',
            'number_decimal_separator' => $user->number_decimal_separator ?: ',',
            'date_format' => $user->date_format ?: 'D MMM YYYY',
            'base_currency_code' => $user->base_currency_code,
            'is_admin' => $user->hasRole('admin'),
            'is_impersonable' => (bool) $user->is_impersonable,
            'is_impersonated' => method_exists($user, 'isImpersonated') ? (bool) $user->isImpersonated() : false,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at?->toJSON(),
            'updated_at' => $user->updated_at?->toJSON(),
            'settings' => $user->settings === null ? null : [
                'uuid' => $user->settings->uuid,
                'active_year' => $user->settings->active_year,
                'base_currency' => $user->base_currency_code,
            ],
        ];
    }

    protected function resolveTransactionsNavigation(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $request->routeIs('dashboard*', 'reports*', 'budget-planning*', 'transactions*', 'recurring-entries*')) {
            return null;
        }

        $contextResolver = app(ManagementContextResolver::class);

        if ($request->routeIs('transactions*')) {
            ['year' => $year, 'month' => $month] = $contextResolver->resolveTransactions($request, $user);
        } elseif ($request->routeIs('recurring-entries*')) {
            ['year' => $year, 'month' => $month] = $contextResolver->resolveDashboard($request, $user);
            $month ??= $year === (int) now(config('app.timezone'))->year
                ? (int) now(config('app.timezone'))->month
                : 1;
        } elseif ($request->routeIs('dashboard*', 'reports*')) {
            ['year' => $year, 'month' => $month] = $contextResolver->resolveDashboard($request, $user);
        } else {
            $year = $contextResolver->resolveYearOnly($request, $user);
            $month = null;
        }

        $navigation = app(TransactionNavigationService::class)->build($user, $year, $month);

        if ($request->routeIs('recurring-entries*')) {
            $query = $request->query();

            $navigation['months'] = collect($navigation['months'])
                ->map(function (array $item) use ($query, $year): array {
                    return [
                        ...$item,
                        'href' => route('recurring-entries.index', [
                            ...$query,
                            'year' => $year,
                            'month' => $item['value'],
                        ]),
                    ];
                })
                ->all();
        }

        return $navigation;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sharedNotificationInbox(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        /** @var UserNotificationInboxService $inboxService */
        $inboxService = app(UserNotificationInboxService::class);

        return [
            'unread_count' => $inboxService->unreadCount($user),
            'latest' => NotificationInboxItemResource::collection($inboxService->latest($user, 6))->resolve(),
            'index_url' => route('notifications.index'),
            'preview_url' => route('notifications.preview'),
            'mark_all_read_url' => route('notifications.mark-all-read'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sharedSessionWarning(Request $request): ?array
    {
        if ($request->user() === null) {
            return null;
        }

        $sessionLifetimeSeconds = max(60, (int) config('session.lifetime', 180) * 60);
        $warningWindowSeconds = min(300, max(30, (int) config('session.warning_window_seconds', 300)));
        $autoKeepAliveThresholdSeconds = min(
            $sessionLifetimeSeconds,
            max(60, (int) config('session.auto_keep_alive_threshold_seconds', 900)),
        );

        return [
            'enabled' => true,
            'expires_at' => now(config('app.timezone'))
                ->addSeconds($sessionLifetimeSeconds)
                ->toIso8601String(),
            'warning_window_seconds' => $warningWindowSeconds,
            'session_lifetime_seconds' => $sessionLifetimeSeconds,
            'auto_keep_alive_enabled' => (bool) config('session.auto_keep_alive_enabled', true),
            'auto_keep_alive_threshold_seconds' => $autoKeepAliveThresholdSeconds,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sharedContextualHelp(Request $request): ?array
    {
        if ($request->routeIs('admin.*')) {
            return null;
        }

        return app(CurrentContextualHelpResolver::class)->resolvePayloadForRequest($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sharedEntrySearch(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $accessibleAccountsQuery = app(AccessibleAccountsQuery::class);
        $accessibleAccountIds = $accessibleAccountsQuery->ids($user);
        $accessibleOwnerIds = $accessibleAccountsQuery->ownerIds($user);
        $categoryOptions = [];

        if ($accessibleOwnerIds !== [] || $accessibleAccountIds !== []) {
            $categoryOptions = Category::query()
                ->where('is_active', true)
                ->where('is_selectable', true)
                ->where(function (Builder $query) use ($accessibleOwnerIds, $accessibleAccountIds): void {
                    if ($accessibleOwnerIds !== []) {
                        $query->where(function (Builder $ownedQuery) use ($accessibleOwnerIds): void {
                            $ownedQuery
                                ->whereNull('account_id')
                                ->whereIn('user_id', $accessibleOwnerIds);
                        });
                    }

                    if ($accessibleAccountIds !== []) {
                        $query->orWhereIn('account_id', $accessibleAccountIds);
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'uuid',
                    'parent_id',
                    'name',
                    'icon',
                    'color',
                    'sort_order',
                    'is_selectable',
                ]);

            $categoryOptions = collect(CategoryHierarchy::buildFlat($categoryOptions))
                ->map(fn (array $category): array => [
                    'value' => (string) $category['uuid'],
                    'label' => (string) ($category['full_path'] ?? $category['name']),
                    'full_path' => (string) ($category['full_path'] ?? $category['name']),
                    'icon' => $category['icon'] ?? null,
                    'color' => $category['color'] ?? null,
                    'ancestor_uuids' => collect($category['ancestor_uuids'] ?? [])->filter()->values()->all(),
                    'is_selectable' => (bool) ($category['is_selectable'] ?? true),
                ])
                ->unique('value')
                ->values()
                ->all();
        }

        return [
            'account_options' => collect($accessibleAccountsQuery->dashboardFilterOptions($user))
                ->map(fn (array $account): array => [
                    'value' => (string) $account['value'],
                    'label' => (string) $account['label'],
                ])
                ->unique('value')
                ->values()
                ->all(),
            'category_options' => $categoryOptions,
        ];
    }

    protected function hasSharedCategories(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $accessibleAccountsQuery = app(AccessibleAccountsQuery::class);
        $sharedAccountCategoryTaxonomyService = app(SharedAccountCategoryTaxonomyService::class);

        return $accessibleAccountsQuery
            ->get($user)
            ->contains(fn ($account): bool => $sharedAccountCategoryTaxonomyService->isSharedAccount($account));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sharedPublicSeo(Request $request): ?array
    {
        return app(PublicPageSeoResolver::class)->resolve($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSharedChangelogMeta(): array
    {
        return Cache::remember(
            'inertia:shared:changelog-meta',
            now(config('app.timezone'))->addMinutes(5),
            function (): array {
                $latestPublishedRelease = ChangelogRelease::query()
                    ->where('is_published', true)
                    ->ordered()
                    ->first(['version_label', 'channel']);

                $indexUrl = route('changelog.index');
                $latestReleaseUrl = $latestPublishedRelease === null
                    ? $indexUrl
                    : route('changelog.show', ['versionLabel' => $latestPublishedRelease->version_label]);

                return [
                    'changelog_url' => $latestReleaseUrl,
                    'changelog' => [
                        'index_url' => $indexUrl,
                        'latest_release_label' => $latestPublishedRelease?->version_label,
                        'latest_release_channel' => $latestPublishedRelease?->channel,
                        'latest_release_url' => $latestReleaseUrl,
                        'has_published_release' => $latestPublishedRelease !== null,
                    ],
                ];
            },
        );
    }

    protected function usesAuthenticatedAppShell(Request $request): bool
    {
        if ($request->user() === null) {
            return false;
        }

        return ! $request->is(
            'login',
            'register',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'two-factor-challenge',
            'user/confirm-password',
            'email/verify',
            'email/verify/*',
            'account-invitations/*/onboarding',
        );
    }

    protected function shouldShareTransactionsNavigation(Request $request): bool
    {
        return $request->user() !== null
            && $request->routeIs('dashboard*', 'reports*', 'budget-planning*', 'transactions*', 'recurring-entries*');
    }

    protected function shouldShareSettingsNavigation(Request $request): bool
    {
        return $request->user() !== null
            && $request->routeIs(
                'profile.*',
                'security.*',
                'years.*',
                'categories.*',
                'shared-categories.*',
                'tracked-items.*',
                'banks.*',
                'accounts.*',
                'appearance.*',
                'exports.*',
                'imports.*',
            );
    }

    protected function isIndexablePublicRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName)
            && app(PublicPageSeoResolver::class)->isIndexablePublicRoute($routeName);
    }

    protected function shouldSharePublicIntegrations(Request $request): bool
    {
        return $request->user() === null && $this->isIndexablePublicRoute($request);
    }

    protected function shouldShareContextualHelp(Request $request): bool
    {
        return $this->usesAuthenticatedAppShell($request)
            && ! $request->routeIs('admin.*');
    }

    protected function shouldShareEntrySearch(Request $request): bool
    {
        return $this->usesAuthenticatedAppShell($request)
            && ! $request->routeIs('admin.*');
    }
}
