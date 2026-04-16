<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DestroyPushDeviceTokenRequest;
use App\Http\Requests\Settings\NotificationPreferencesUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\ShowPushDeviceStatusRequest;
use App\Http\Requests\Settings\StorePushDeviceTokenRequest;
use App\Models\CommunicationCategory;
use App\Models\DeviceToken;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\UserSetting;
use App\Services\Auth\ActiveSessionService;
use App\Services\Billing\ProfileSupportSummaryService;
use App\Services\Communication\CommunicationPreferenceCatalog;
use App\Services\Push\DeviceTokenService;
use App\Supports\Currency\CurrencySupport;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(
        protected CommunicationPreferenceCatalog $preferenceCatalog,
        protected ActiveSessionService $activeSessionService,
        protected ProfileSupportSummaryService $profileSupportSummaryService,
    ) {}

    /**
     * Show the mobile settings launcher entry point.
     */
    public function index(Request $request): Response
    {
        return $this->edit($request);
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $canUpdateBaseCurrency = $user->canChangeBaseCurrency();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'preferences' => [
                'locale' => $user->locale,
                'format_locale' => $user->format_locale,
                'number_thousands_separator' => $user->number_thousands_separator ?: '.',
                'number_decimal_separator' => $user->number_decimal_separator ?: ',',
                'date_format' => $user->date_format ?: 'D MMM YYYY',
                'base_currency_code' => $user->base_currency_code,
                'can_update_base_currency' => $canUpdateBaseCurrency,
                'base_currency_lock_message' => $canUpdateBaseCurrency
                    ? null
                    : __('settings.profile.currency_locked_after_transactions'),
            ],
            'notification_preferences' => $this->notificationPreferencesPayload($user),
            'active_sessions' => [
                'current_session_id' => $request->session()->getId(),
                'items' => $this->activeSessionService->forUser(
                    $user,
                    $request->session()->getId(),
                ),
            ],
            'support' => $this->profileSupportSummaryService->forUser($user),
            'options' => [
                'locales' => collect(config('locales.supported', []))
                    ->map(
                        fn (array $locale): array => [
                            'code' => $locale['code'],
                            'label' => $locale['label'],
                        ]
                    )
                    ->values()
                    ->all(),
                'format_locales' => collect(config('currencies.format_locales', []))
                    ->map(
                        fn (string $label, string $code): array => [
                            'code' => $code,
                            'label' => $label,
                        ]
                    )
                    ->values()
                    ->all(),
                'number_thousands_separators' => collect(config('currencies.format_preferences.thousands_separators', []))
                    ->map(
                        fn (string $value, string $key): array => [
                            'key' => $key,
                            'value' => $value,
                        ]
                    )
                    ->values()
                    ->all(),
                'number_decimal_separators' => collect(config('currencies.format_preferences.decimal_separators', []))
                    ->map(
                        fn (string $value, string $key): array => [
                            'key' => $key,
                            'value' => $value,
                        ]
                    )
                    ->values()
                    ->all(),
                'date_formats' => collect(config('currencies.format_preferences.date_formats', []))
                    ->map(
                        fn (string $value): array => [
                            'value' => $value,
                        ]
                    )
                    ->values()
                    ->all(),
                'base_currencies' => collect(app(CurrencySupport::class)->options())
                    ->map(
                        fn (array $currency): array => [
                            'code' => $currency['code'],
                            'name' => $currency['name'],
                            'symbol' => $currency['symbol'],
                            'label' => sprintf(
                                '%s — %s (%s)',
                                $currency['code'],
                                $currency['name'],
                                $currency['symbol']
                            ),
                        ]
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $user->fill(Arr::only($validated, [
            'name',
            'surname',
            'email',
            'format_locale',
            'number_thousands_separator',
            'number_decimal_separator',
            'date_format',
        ]));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->boolean('avatar_remove')) {
            $user->removeAvatar();
        } elseif ($request->hasFile('avatar_image')) {
            $user->replaceAvatar($request->file('avatar_image'));
        }

        return to_route('profile.edit');
    }

    public function avatar(Request $request, User $user): StreamedResponse
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();

        abort_unless(
            $authenticatedUser->is($user) || $authenticatedUser->isAdmin(),
            403,
        );

        abort_unless(
            is_string($user->avatar_path) && $user->avatar_path !== '',
            404,
        );

        abort_unless(Storage::disk('public')->exists($user->avatar_path), 404);

        return Storage::disk('public')->response($user->avatar_path);
    }

    public function updateNotificationPreferences(NotificationPreferencesUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $categories = $this->configurableCategories()->get()->keyBy('uuid');

        foreach ($request->validated('categories', []) as $categoryPreference) {
            $category = $categories->get($categoryPreference['uuid']);

            if (! $category instanceof CommunicationCategory) {
                continue;
            }

            $topicKey = $this->preferenceCatalog->topicKeyForCategory($category->key);
            $topic = NotificationTopic::query()->where('key', $topicKey)->first();

            if (! $topic instanceof NotificationTopic) {
                continue;
            }

            $availableChannels = $this->preferenceCatalog->availableChannels($category);

            $payload = [
                'email_enabled' => $availableChannels['email']
                    ? (bool) ($categoryPreference['email_enabled'] ?? false)
                    : false,
                'in_app_enabled' => $availableChannels['in_app']
                    ? (bool) ($categoryPreference['in_app_enabled'] ?? false)
                    : false,
                'sms_enabled' => false,
            ];

            UserNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_topic_id' => $topic->id,
                ],
                $payload,
            );
        }

        if (config('features.push_notifications.enabled')) {
            $this->storePushPreference(
                $user,
                (bool) data_get($request->validated(), 'push.enabled', true),
            );
        }

        return back()->with('success', __('settings.profile.notification_preferences_updated'));
    }

    public function storePushToken(
        StorePushDeviceTokenRequest $request,
        DeviceTokenService $deviceTokenService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $deviceRegistration = $deviceTokenService->registerCurrentDevice(
            $user,
            $request->string('token')->toString(),
            $request->string('platform')->toString(),
            $request->string('locale')->toString() ?: null,
            $request->string('device_identifier')->toString() ?: null,
            $request->string('service_worker_version')->toString() ?: null,
        );

        $this->storePushPreference($user, true);

        return response()->json([
            'message' => __('settings.profile.push_web.flash.enabled'),
            'push' => [
                'enabled' => true,
                'current_device_enabled' => true,
                'active_tokens_count' => $deviceTokenService->activeBroadcastTokenCountForUser($user),
                'device_lifecycle' => $deviceRegistration['lifecycle'],
                'recovered_from_invalidation' => $deviceRegistration['recovered_from_invalidation'],
            ],
        ]);
    }

    public function showPushDeviceStatus(
        ShowPushDeviceStatusRequest $request,
        DeviceTokenService $deviceTokenService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $platform = $request->string('platform')->toString() ?: 'web';
        $token = $request->string('token')->toString();
        $deviceIdentifier = $request->string('device_identifier')->toString() ?: null;
        $activeDeviceToken = $deviceTokenService->currentDeviceTokenForUser(
            $user,
            $deviceIdentifier,
            $token,
            $platform,
        );
        $tokenMismatch = $activeDeviceToken instanceof DeviceToken
            && $token !== ''
            && $activeDeviceToken->token !== $token;

        if ($tokenMismatch) {
            Log::info('Push device token mismatch detected.', [
                'user_id' => $user->getKey(),
                'platform' => $platform,
                'device_identifier' => $deviceIdentifier !== null
                    ? substr(hash('sha256', $deviceIdentifier), 0, 12)
                    : null,
                'backend_token_hash' => substr(hash('sha256', $activeDeviceToken->token), 0, 12),
                'browser_token_hash' => substr(hash('sha256', $token), 0, 12),
            ]);
        }

        return response()->json([
            'push' => [
                'global_enabled' => (bool) data_get(
                    $user->settings?->settings,
                    'notifications.push.enabled',
                    true,
                ),
                'current_device_enabled' => $activeDeviceToken !== null,
                'active_tokens_count' => $deviceTokenService->activeBroadcastTokenCountForUser($user),
                'token_mismatch' => $tokenMismatch,
                'device_lifecycle' => $activeDeviceToken?->invalidation_reason === null
                    ? 'stable'
                    : 'recoverable',
            ],
        ]);
    }

    public function destroyPushToken(
        DestroyPushDeviceTokenRequest $request,
        DeviceTokenService $deviceTokenService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $platform = $request->string('platform')->toString() ?: 'web';
        $token = $request->string('token')->toString();
        $deviceIdentifier = $request->string('device_identifier')->toString() ?: null;

        if ($token !== '' || $deviceIdentifier !== null) {
            $deviceTokenService->markInactiveCurrentDevice(
                $user,
                $deviceIdentifier,
                $token,
                $platform,
            );
        }

        return response()->json([
            'message' => __('settings.profile.push_web.flash.disabled'),
            'push' => [
                'enabled' => (bool) data_get(
                    $user->settings?->settings,
                    'notifications.push.enabled',
                    true,
                ),
                'active_tokens_count' => $deviceTokenService->activeBroadcastTokenCountForUser($user),
            ],
        ]);
    }

    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($sessionId === $request->session()->getId(), 422, __('settings.profile.active_sessions.validation.current_session'));

        $deleted = $this->activeSessionService->revokeUserSession(
            $user,
            $sessionId,
            $request->session()->getId(),
        );

        abort_unless($deleted, 404);

        return to_route('profile.edit')->with('success', __('settings.profile.active_sessions.flash.single_revoked'));
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revokedCount = $this->activeSessionService->revokeOtherUserSessions(
            $user,
            $request->session()->getId(),
        );

        return to_route('profile.edit')->with('success', __('settings.profile.active_sessions.flash.others_revoked', [
            'count' => $revokedCount,
        ]));
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationPreferencesPayload(User $user): array
    {
        $user->loadMissing('notificationPreferences');

        $categories = $this->configurableCategories()
            ->with('channelTemplates')
            ->orderBy('name')
            ->get();

        return [
            'push' => [
                'visible' => (bool) config('features.push_notifications.enabled')
                    && (bool) config('features.push_notifications.profile_enabled'),
                'enabled' => (bool) data_get($user->settings?->settings, 'notifications.push.enabled', true),
                'active_tokens_count' => app(DeviceTokenService::class)->activeBroadcastTokenCountForUser($user),
            ],
            'categories' => $categories
                ->map(function (CommunicationCategory $category) use ($user): array {
                    $topicKey = $this->preferenceCatalog->topicKeyForCategory($category->key);
                    $topic = NotificationTopic::query()->where('key', $topicKey)->first();
                    $preference = $topic
                        ? $user->notificationPreferences->firstWhere('notification_topic_id', $topic->id)
                        : null;
                    $availableChannels = $this->preferenceCatalog->availableChannels($category);

                    return [
                        'uuid' => $category->uuid,
                        'key' => $category->key,
                        'label' => $this->translatedTopicValue(
                            "settings.profile.notifications.categories.{$this->translationSegmentForCategory($category->key)}.label",
                            $category->name,
                        ),
                        'description' => $this->translatedTopicValue(
                            "settings.profile.notifications.categories.{$this->translationSegmentForCategory($category->key)}.description",
                            $category->description,
                        ),
                        'channels' => $availableChannels,
                        'preferences' => [
                            'email_enabled' => $availableChannels['email']
                                ? (bool) ($preference?->email_enabled ?? $topic?->default_email_enabled ?? false)
                                : false,
                            'in_app_enabled' => $availableChannels['in_app']
                                ? (bool) ($preference?->in_app_enabled ?? $topic?->default_in_app_enabled ?? false)
                                : false,
                        ],
                        'defaults' => [
                            'email_enabled' => $availableChannels['email']
                                ? (bool) ($topic?->default_email_enabled ?? false)
                                : false,
                            'in_app_enabled' => $availableChannels['in_app']
                                ? (bool) ($topic?->default_in_app_enabled ?? false)
                                : false,
                        ],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    protected function storePushPreference(User $user, bool $enabled): void
    {
        /** @var UserSetting $settings */
        $settings = UserSetting::query()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $settings->active_year ??= $user->settings?->active_year;
        $settings->base_currency ??= $user->settings?->base_currency ?? $user->base_currency_code;
        $settings->settings ??= [];

        $currentSettings = $settings->settings ?? [];
        data_set($currentSettings, 'notifications.push.enabled', $enabled);

        $settings->forceFill([
            'settings' => $currentSettings,
        ])->save();
    }

    protected function configurableCategories()
    {
        return $this->preferenceCatalog->configurableCategoriesQuery();
    }

    protected function translatedTopicValue(string $key, ?string $fallback = null): string
    {
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $fallback ?? $key;
    }

    protected function translationSegmentForCategory(string $categoryKey): string
    {
        return str_replace('.', '.', $categoryKey);
    }
}
