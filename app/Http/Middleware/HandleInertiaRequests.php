<?php

namespace App\Http\Middleware;

use App\Services\Transactions\TransactionNavigationService;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\Request;
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
        return parent::version($request);
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->sharedAuthUser($request),
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'transactionsNavigation' => fn (): ?array => $this->resolveTransactionsNavigation($request),
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
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at?->toJSON(),
            'updated_at' => $user->updated_at?->toJSON(),
            'settings' => $user->settings === null ? null : [
                'uuid' => $user->settings->uuid,
                'active_year' => $user->settings->active_year,
                'base_currency' => $user->settings->base_currency,
            ],
        ];
    }

    protected function resolveTransactionsNavigation(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $request->routeIs('dashboard*', 'budget-planning*', 'transactions*')) {
            return null;
        }

        $contextResolver = app(ManagementContextResolver::class);

        if ($request->routeIs('transactions*')) {
            ['year' => $year, 'month' => $month] = $contextResolver->resolveTransactions($request, $user);
        } elseif ($request->routeIs('dashboard*')) {
            ['year' => $year, 'month' => $month] = $contextResolver->resolveDashboard($request, $user);
        } else {
            $year = $contextResolver->resolveYearOnly($request, $user);
            $month = null;
        }

        return app(TransactionNavigationService::class)->build($user, $year, $month);
    }
}
