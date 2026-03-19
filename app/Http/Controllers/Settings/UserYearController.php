<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserYearRequest;
use App\Http\Requests\Settings\UpdateUserYearRequest;
use App\Models\User;
use App\Models\UserYear;
use App\Services\UserYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserYearController extends Controller
{
    public function __construct(
        protected UserYearService $userYearService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Years', $payload);
    }

    public function store(StoreUserYearRequest $request): RedirectResponse
    {
        $user = $request->user();
        $year = UserYear::query()->create([
            'user_id' => $user->id,
            'year' => $request->integer('year'),
            'is_closed' => false,
        ]);

        if ($user->settings?->active_year === null || $user->years()->count() === 1) {
            $this->userYearService->syncActiveYear($user, $year->year);
            $request->session()->put('dashboard_year', $year->year);
            $request->session()->forget('dashboard_month');
        }

        return to_route('years.edit')->with('success', "Anno {$year->year} creato correttamente.");
    }

    public function activate(Request $request, UserYear $userYear): RedirectResponse
    {
        $userYear = $this->ownedYear($request, $userYear);

        $this->userYearService->syncActiveYear($request->user(), $userYear->year);
        $request->session()->put('dashboard_year', $userYear->year);
        $request->session()->forget('dashboard_month');

        return to_route('years.edit')->with('success', "Anno {$userYear->year} impostato come attivo.");
    }

    public function update(UpdateUserYearRequest $request, UserYear $userYear): RedirectResponse
    {
        $userYear = $this->ownedYear($request, $userYear);
        $userYear->forceFill([
            'is_closed' => $request->boolean('is_closed'),
        ])->save();

        return to_route('years.edit')->with(
            'success',
            $userYear->is_closed
                ? "Anno {$userYear->year} chiuso correttamente."
                : "Anno {$userYear->year} riaperto correttamente."
        );
    }

    public function destroy(Request $request, UserYear $userYear): RedirectResponse
    {
        $user = $request->user();
        $userYear = $this->ownedYear($request, $userYear);
        $blockingReasons = $this->userYearService->deletionBlockingReasons($user, $userYear);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => "L'anno {$userYear->year} non può essere eliminato: ".implode(', ', $blockingReasons).'.',
            ]);
        }

        $userYear->delete();

        return to_route('years.edit')->with('success', "Anno {$userYear->year} eliminato correttamente.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(User $user): array
    {
        $years = UserYear::query()
            ->where('user_id', $user->id)
            ->orderByDesc('year')
            ->get([
                'id',
                'user_id',
                'year',
                'is_closed',
            ]);

        $user->loadMissing('settings', 'years');
        $usageSummary = $this->userYearService->usageSummary($user);
        $activeYear = $user->settings?->active_year;
        $maxYear = $years->max('year');
        $totalYears = $years->count();

        $items = $years->map(function (UserYear $year) use ($usageSummary, $activeYear, $totalYears): array {
            $usage = $usageSummary[$year->year] ?? [
                'counts' => [
                    'budgets' => 0,
                    'transactions' => 0,
                    'scheduled_entries' => 0,
                    'recurring_occurrences' => 0,
                    'recurring_entries' => 0,
                ],
                'usage_count' => 0,
                'used' => false,
                'is_deletable' => true,
            ];

            return [
                'id' => $year->id,
                'year' => $year->year,
                'is_closed' => (bool) $year->is_closed,
                'is_active' => $activeYear === $year->year,
                'counts' => $usage['counts'],
                'usage_count' => (int) $usage['usage_count'],
                'used' => (bool) $usage['used'],
                'is_deletable' => (bool) $usage['is_deletable'] && $activeYear !== $year->year && $totalYears > 1,
            ];
        })->values()->all();

        return [
            'years' => [
                'data' => $items,
                'summary' => [
                    'total_count' => count($items),
                    'open_count' => collect($items)->where('is_closed', false)->count(),
                    'closed_count' => collect($items)->where('is_closed', true)->count(),
                    'used_count' => collect($items)->where('used', true)->count(),
                    'active_year' => $activeYear,
                ],
                'meta' => [
                    'next_year' => $maxYear !== null ? $maxYear + 1 : now()->year,
                    'current_calendar_year' => now()->year,
                ],
            ],
        ];
    }

    protected function ownedYear(Request $request, UserYear $userYear): UserYear
    {
        abort_unless($userYear->user_id === $request->user()->id, 404);

        return $userYear;
    }
}
