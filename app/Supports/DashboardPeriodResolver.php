<?php

namespace App\Supports;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardPeriodResolver
{
    public static function resolve(Request $request, User $user): array
    {
        $settings = $user->settings;

        $availableYears = $user->years()
            ->pluck('year')
            ->merge(
                Transaction::query()
                    ->where('user_id', $user->id)
                    ->pluck('transaction_date')
                    ->map(fn ($date) => (int) date('Y', strtotime((string) $date)))
            )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $fallbackYear = $settings?->active_year
            ?: (! empty($availableYears) ? max($availableYears) : now()->year);

        $requestedYear = (int) (
            $request->integer('year')
                ?: session('dashboard_year')
                ?: $fallbackYear
        );

        $year = empty($availableYears) || in_array($requestedYear, $availableYears, true)
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

    public static function persist(User $user, int $year, ?int $month): void
    {
        session([
            'dashboard_year' => $year,
            'dashboard_month' => $month,
        ]);

        if ($user->settings?->active_year !== $year) {
            $user->settings()->updateOrCreate([], [
                'active_year' => $year,
            ]);
            $user->unsetRelation('settings');
            $user->load('settings');
        }
    }
}
