<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferencesUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\CommunicationCategory;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Communication\CommunicationPreferenceCatalog;
use App\Supports\Currency\CurrencySupport;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        protected CommunicationPreferenceCatalog $preferenceCatalog,
    ) {}

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
                'base_currency_code' => $user->base_currency_code,
                'can_update_base_currency' => $canUpdateBaseCurrency,
                'base_currency_lock_message' => $canUpdateBaseCurrency
                    ? null
                    : __('settings.profile.currency_locked_after_accounts_or_transactions'),
            ],
            'notification_preferences' => $this->notificationPreferencesPayload($user),
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
                'base_currencies' => collect(app(CurrencySupport::class)->options())
                    ->map(
                        fn (array $currency): array => [
                            'code' => $currency['code'],
                            'label' => sprintf('%s (%s)', $currency['name'], $currency['code']),
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
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
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

        return back()->with('success', __('settings.profile.notification_preferences_updated'));
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
