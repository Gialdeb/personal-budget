<?php

namespace App\Supports;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\UserYearService;
use Illuminate\Http\Request;

class ManagementContextResolver
{
    public function __construct(
        protected UserYearService $userYearService
    ) {}

    /**
     * @return array{year: int, month: int|null}
     */
    public function resolveDashboard(Request $request, User $user): array
    {
        $availableYears = $this->availableYears($user);
        $hasRegisteredYears = $this->hasRegisteredYears($user);
        $fallbackYear = $this->fallbackYear($user, $availableYears, $hasRegisteredYears);

        $requestedYear = (int) (
            $request->integer('year')
                ?: $user->settings?->active_year
                ?: session('dashboard_year')
                ?: $fallbackYear
        );

        $year = ! $hasRegisteredYears || in_array($requestedYear, $availableYears, true)
            ? $requestedYear
            : $fallbackYear;

        $month = PeriodOptions::normalizeMonth(
            $request->has('month')
                ? $request->input('month')
                : ($request->has('year') ? null : session('dashboard_month'))
        );

        return [
            'year' => $year,
            'month' => $month,
        ];
    }

    public function resolveYearOnly(Request $request, User $user): int
    {
        $availableYears = $this->availableYears($user);
        $hasRegisteredYears = $this->hasRegisteredYears($user);
        $fallbackYear = $this->fallbackYear($user, $availableYears, $hasRegisteredYears);
        $requestedYear = (int) (
            $request->integer('year')
                ?: $user->settings?->active_year
                ?: session('dashboard_year')
                ?: $fallbackYear
        );

        return ! $hasRegisteredYears || in_array($requestedYear, $availableYears, true)
            ? $requestedYear
            : $fallbackYear;
    }

    /**
     * @return array{year: int, month: int}
     */
    public function resolveTransactions(Request $request, User $user): array
    {
        $availableYears = $this->availableYears($user);
        $hasRegisteredYears = $this->hasRegisteredYears($user);
        $fallbackYear = $this->fallbackYear($user, $availableYears, $hasRegisteredYears);
        $routeYear = (int) $request->route('year');
        $year = ! $hasRegisteredYears || in_array($routeYear, $availableYears, true)
            ? $routeYear
            : $fallbackYear;

        $month = PeriodOptions::normalizeMonth($request->route('month'));

        return [
            'year' => $year,
            'month' => $month ?? 1,
        ];
    }

    public function persist(User $user, int $year, ?int $month = null, bool $persistMonth = true): void
    {
        $session = [
            'dashboard_year' => $year,
        ];

        if ($persistMonth) {
            $session['dashboard_month'] = $month;
        }

        session($session);

        if ($user->settings?->active_year !== $year) {
            /** @var UserSetting $settings */
            $settings = $user->settings()->firstOrNew();
            $settings->active_year = $year;
            $settings->save();

            $user->unsetRelation('settings');
            $user->load('settings');
        }
    }

    /**
     * @return array<int, int>
     */
    protected function availableYears(User $user): array
    {
        return $this->userYearService->availableYears($user);
    }

    protected function hasRegisteredYears(User $user): bool
    {
        return $this->userYearService->registeredYears($user) !== [];
    }

    /**
     * @param  array<int, int>  $availableYears
     */
    protected function fallbackYear(User $user, array $availableYears, bool $hasRegisteredYears): int
    {
        return $user->settings?->active_year !== null
            && (! $hasRegisteredYears || in_array($user->settings->active_year, $availableYears, true))
            ? $user->settings->active_year
            : ($availableYears !== [] ? max($availableYears) : now()->year);
    }
}
