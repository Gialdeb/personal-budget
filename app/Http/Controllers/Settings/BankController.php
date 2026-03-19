<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserBankRequest;
use App\Http\Requests\Settings\UpdateUserBankRequest;
use App\Models\Bank;
use App\Models\UserBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BankController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Banks', $payload);
    }

    public function store(StoreUserBankRequest $request): RedirectResponse
    {
        $mode = $request->string('mode')->value();

        if ($mode === 'catalog') {
            $bank = Bank::query()->findOrFail($request->integer('bank_id'));

            UserBank::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'bank_id' => $bank->id,
                ],
                [
                    'name' => $bank->name,
                    'slug' => $bank->slug,
                    'is_custom' => false,
                    'is_active' => true,
                ]
            );

            return to_route('banks.edit')->with('success', 'Banca dal catalogo aggiunta correttamente.');
        }

        UserBank::query()->create([
            'user_id' => $request->user()->id,
            'bank_id' => null,
            'name' => (string) $request->validated('name'),
            'slug' => (string) $request->validated('slug'),
            'is_custom' => true,
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        return to_route('banks.edit')->with('success', 'Banca personalizzata creata correttamente.');
    }

    public function update(UpdateUserBankRequest $request, UserBank $userBank): RedirectResponse
    {
        $userBank = $this->ownedUserBank($request, $userBank);

        if (! $userBank->is_custom) {
            throw ValidationException::withMessages([
                'name' => 'Solo le banche personalizzate possono essere modificate.',
            ]);
        }

        $userBank->fill($request->validated());
        $userBank->save();

        return to_route('banks.edit')->with('success', 'Banca personalizzata aggiornata correttamente.');
    }

    public function toggleActive(Request $request, UserBank $userBank): RedirectResponse
    {
        $userBank = $this->ownedUserBank($request, $userBank);

        $userBank->forceFill([
            'is_active' => ! $userBank->is_active,
        ])->save();

        return to_route('banks.edit')->with(
            'success',
            $userBank->is_active
                ? 'Banca attivata correttamente.'
                : 'Banca disattivata correttamente.'
        );
    }

    public function destroy(Request $request, UserBank $userBank): RedirectResponse
    {
        $userBank = $this->ownedUserBank($request, $userBank);

        $blockingReasons = $this->blockingReasons($userBank);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => 'Questa banca non può essere rimossa: '.implode(', ', $blockingReasons).'. Disattivala invece per toglierla dalla selezione operativa.',
            ]);
        }

        $userBank->delete();

        return to_route('banks.edit')->with('success', 'Banca rimossa correttamente dalle tue banche disponibili.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(int $userId): array
    {
        $userBanks = UserBank::query()
            ->ownedBy($userId)
            ->with('bank:id,name,slug,country_code,is_active')
            ->withCount('accounts')
            ->orderByDesc('is_active')
            ->orderByDesc('is_custom')
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'bank_id',
                'name',
                'slug',
                'is_custom',
                'is_active',
            ]);

        $userBankItems = $userBanks->map(function (UserBank $userBank): array {
            $accountsCount = (int) $userBank->accounts_count;

            return [
                'id' => $userBank->id,
                'bank_id' => $userBank->bank_id,
                'name' => $userBank->name,
                'slug' => $userBank->slug,
                'is_custom' => (bool) $userBank->is_custom,
                'is_active' => (bool) $userBank->is_active,
                'source_label' => $userBank->is_custom ? 'Personalizzata' : 'Globale',
                'catalog_bank' => $userBank->bank === null ? null : [
                    'id' => $userBank->bank->id,
                    'name' => $userBank->bank->name,
                    'slug' => $userBank->bank->slug,
                    'country_code' => $userBank->bank->country_code,
                ],
                'accounts_count' => $accountsCount,
                'used' => $accountsCount > 0,
                'is_deletable' => $accountsCount === 0,
            ];
        })->values()->all();

        $catalogBankIds = $userBanks->pluck('bank_id')->filter()->values()->all();

        return [
            'banks' => [
                'data' => $userBankItems,
                'summary' => [
                    'total_count' => count($userBankItems),
                    'active_count' => collect($userBankItems)->where('is_active', true)->count(),
                    'custom_count' => collect($userBankItems)->where('is_custom', true)->count(),
                    'catalog_count' => collect($userBankItems)->where('is_custom', false)->count(),
                    'used_count' => collect($userBankItems)->where('used', true)->count(),
                ],
            ],
            'catalog' => [
                'available' => Bank::query()
                    ->where('is_active', true)
                    ->when(
                        $catalogBankIds !== [],
                        fn ($query) => $query->whereNotIn('id', $catalogBankIds)
                    )
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'country_code'])
                    ->map(fn (Bank $bank): array => [
                        'id' => $bank->id,
                        'name' => $bank->name,
                        'slug' => $bank->slug,
                        'country_code' => $bank->country_code,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function ownedUserBank(Request $request, UserBank $userBank): UserBank
    {
        abort_unless($userBank->user_id === $request->user()->id, 404);

        return $userBank;
    }

    /**
     * @return array<int, string>
     */
    protected function blockingReasons(UserBank $userBank): array
    {
        $userBank->loadCount('accounts');

        $reasons = [];

        if ($userBank->accounts_count > 0) {
            $reasons[] = $userBank->accounts_count === 1
                ? 'è collegata a 1 account'
                : "è collegata a {$userBank->accounts_count} account";
        }

        return $reasons;
    }
}
