<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserBankRequest;
use App\Http\Requests\Settings\UpdateUserBankRequest;
use App\Models\Account;
use App\Models\Bank;
use App\Models\UserBank;
use App\Services\Accounts\AccountProvisioningService;
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
        $createBaseAccount = (bool) $request->validated('create_base_account');
        $provisioningService = app(AccountProvisioningService::class);

        if ($mode === 'catalog') {
            $bank = Bank::query()->findOrFail($request->integer('bank_id'));

            $userBank = UserBank::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'bank_id' => $bank->id,
                ],
                [
                    'name' => $bank->presentableName(),
                    'slug' => $bank->slug,
                    'is_custom' => false,
                    'is_active' => true,
                ]
            );

            $baseAccount = $createBaseAccount
                ? $provisioningService->ensureBaseAccountForUserBank($request->user(), $userBank)
                : null;

            return to_route('banks.edit')->with(
                'success',
                $baseAccount instanceof Account
                    ? __('settings.banks.flash.catalog_created_with_account')
                    : __('settings.banks.flash.catalog_created')
            );
        }

        $userBank = UserBank::query()->create([
            'user_id' => $request->user()->id,
            'bank_id' => null,
            'name' => (string) $request->validated('name'),
            'slug' => (string) $request->validated('slug'),
            'is_custom' => true,
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        $baseAccount = $createBaseAccount
            ? $provisioningService->ensureBaseAccountForUserBank($request->user(), $userBank)
            : null;

        return to_route('banks.edit')->with(
            'success',
            $baseAccount instanceof Account
                ? __('settings.banks.flash.custom_created_with_account')
                : __('settings.banks.flash.custom_created')
        );
    }

    public function update(UpdateUserBankRequest $request, UserBank $userBank): RedirectResponse
    {
        $userBank = $this->ownedUserBank($request, $userBank);

        if (! $userBank->is_custom) {
            throw ValidationException::withMessages([
                'name' => __('settings.banks.validation.custom_only'),
            ]);
        }

        $userBank->fill($request->validated());
        $userBank->save();

        return to_route('banks.edit')->with('success', __('settings.banks.flash.updated'));
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
                ? __('settings.banks.flash.activated')
                : __('settings.banks.flash.deactivated')
        );
    }

    public function destroy(Request $request, UserBank $userBank): RedirectResponse
    {
        $userBank = $this->ownedUserBank($request, $userBank);

        $blockingReasons = $this->blockingReasons($userBank);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => __('settings.banks.validation.delete_blocked', [
                    'reasons' => implode(', ', $blockingReasons),
                ]),
            ]);
        }

        $userBank->delete();

        return to_route('banks.edit')->with('success', __('settings.banks.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(int $userId): array
    {
        $userBanks = UserBank::query()
            ->ownedBy($userId)
            ->with('bank:id,uuid,name,display_name,slug,country_code,logo_url')
            ->withCount('accounts')
            ->orderByDesc('is_active')
            ->orderByDesc('is_custom')
            ->orderBy('name')
            ->get([
                'uuid',
                'bank_id',
                'name',
                'slug',
                'is_custom',
                'is_active',
            ]);

        $userBankItems = $userBanks->map(function (UserBank $userBank): array {
            $accountsCount = (int) $userBank->accounts_count;

            return [
                'uuid' => $userBank->uuid,
                'bank_uuid' => $userBank->bank?->uuid,
                'name' => $userBank->name,
                'display_name' => $userBank->is_custom
                    ? $userBank->name
                    : $this->presentableBankName($userBank->bank, $userBank->name),
                'slug' => $userBank->slug,
                'is_custom' => (bool) $userBank->is_custom,
                'is_active' => (bool) $userBank->is_active,
                'source_label' => $userBank->is_custom ? __('settings.banks.source.custom') : __('settings.banks.source.catalog'),
                'catalog_bank' => $userBank->bank === null ? null : [
                    'uuid' => $userBank->bank->uuid,
                    'name' => $userBank->bank->name,
                    'display_name' => $this->presentableBankName($userBank->bank),
                    'slug' => $userBank->bank->slug,
                    'country_code' => $userBank->bank->country_code,
                    'logo_url' => $userBank->bank->logo_url,
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
                    ->orderByRaw('coalesce(display_name, name)')
                    ->get(['uuid', 'name', 'display_name', 'slug', 'country_code', 'logo_url'])
                    ->map(fn (Bank $bank): array => [
                        'uuid' => $bank->uuid,
                        'name' => $bank->name,
                        'display_name' => $this->presentableBankName($bank),
                        'slug' => $bank->slug,
                        'country_code' => $bank->country_code,
                        'logo_url' => $bank->logo_url,
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
                ? __('settings.banks.delete_reasons.account_one')
                : __('settings.banks.delete_reasons.account_many', ['count' => $userBank->accounts_count]);
        }

        return $reasons;
    }

    protected function presentableBankName(?Bank $bank, ?string $fallback = null): ?string
    {
        if ($bank !== null) {
            return $bank->presentableName();
        }

        return $fallback;
    }
}
