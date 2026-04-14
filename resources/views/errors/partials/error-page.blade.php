@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;

    $supportedLocales = array_keys(config('locales.supported', ['it' => ['code' => 'it'], 'en' => ['code' => 'en']]));
    $normalizeLocale = static function (?string $locale) use ($supportedLocales): ?string {
        if (! is_string($locale) || $locale === '') {
            return null;
        }

        $normalized = Str::of($locale)->replace('_', '-')->before('-')->lower()->value();

        return in_array($normalized, $supportedLocales, true) ? $normalized : null;
    };

    $defaultLocale = $normalizeLocale(config('locales.default', config('app.locale', 'it'))) ?? 'it';
    $authenticatedLocale = $normalizeLocale(Auth::user()?->locale);
    $sessionLocale = request()?->hasSession() === true
        ? $normalizeLocale(request()->session()->get('locale'))
        : null;
    $appLocale = $normalizeLocale(app()->getLocale());
    $browserLocale = $normalizeLocale(request()?->getPreferredLanguage($supportedLocales));

    $resolvedLocale = $authenticatedLocale
        ?? $sessionLocale
        ?? (($appLocale !== null && $appLocale !== $defaultLocale) ? $appLocale : null)
        ?? $browserLocale
        ?? $defaultLocale;

    app()->setLocale($resolvedLocale);

    $homeUrl = Route::has('home') ? route('home') : url('/');
    $dashboardUrl = Route::has('dashboard') ? route('dashboard') : $homeUrl;
    $showHomeCta = ($showHomeCta ?? true) === true;
    $showDashboardCta = ($showDashboardCta ?? true) === true
        && Auth::check()
        && Route::has('dashboard');
    $showReloadCta = ($showReloadCta ?? false) === true;
    $translationNamespace = sprintf('errors.%s', $translationKey);
    $badge = __($translationNamespace . '.badge');
    $title = __($translationNamespace . '.title');
    $message = __($translationNamespace . '.message');
    $detail = __($translationNamespace . '.detail');
    $visualTitle = __($translationNamespace . '.visual_title');
    $visualBody = __($translationNamespace . '.visual_body');
    $nextStepCopy = $showDashboardCta
        ? __('errors.shared.dashboard_hint')
        : ($showReloadCta ? __('errors.shared.reload_hint') : __('errors.shared.home_hint'));
    $pageTitle = sprintf('%s · %s · %s', $statusCode, $title, config('app.name', 'Soamco Budget'));
    $statusChipClass = $statusChipClass ?? 'border-[#f1ddd7] bg-[#fff4ef] text-[#c85d48]';
    $panelGradient = $panelGradient ?? 'from-[#fff7f2] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900';
    $visualGradient = $visualGradient ?? 'from-[#111827] via-[#0f172a] to-[#0f766e]';
    $iconToneClass = $iconToneClass ?? 'from-[#ea5a47] to-[#f59e0b]';
@endphp
<!DOCTYPE html>
<html lang="{{ $resolvedLocale }}" class="h-full bg-[#fffdfb]">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="#ea5a47">
        <title>{{ $pageTitle }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @vite(['resources/js/app.ts'])
        <script>
            (function () {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
        <style>
            html {
                background-color: #fffdfb;
            }

            /*noinspection CssUnusedSymbol*/
            html.dark {
                background-color: #020617;
            }
        </style>
    </head>
    <body class="min-h-screen bg-[#fffdfb] font-sans text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-50">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_left,rgba(234,90,71,0.12),transparent_28%),radial-gradient(circle_at_bottom_right,rgba(14,165,233,0.12),transparent_24%),linear-gradient(180deg,rgba(255,253,251,0.98),rgba(255,250,246,0.96))] dark:bg-[radial-gradient(circle_at_top_left,rgba(234,90,71,0.16),transparent_26%),radial-gradient(circle_at_bottom_right,rgba(16,185,129,0.14),transparent_22%),linear-gradient(180deg,rgba(2,6,23,0.98),rgba(3,7,18,0.96))]"></div>
            <div class="absolute top-10 left-8 -z-10 h-40 w-40 rounded-full bg-[#ffd7cb]/60 blur-3xl dark:bg-[#ea5a47]/15"></div>
            <div class="absolute right-0 bottom-0 -z-10 h-56 w-56 rounded-full bg-sky-200/60 blur-3xl dark:bg-emerald-400/10"></div>

            <div class="mx-auto flex min-h-screen max-w-7xl items-center px-5 py-10 sm:px-8 lg:px-10">
                <div class="grid w-full gap-6 lg:grid-cols-[minmax(0,1.08fr)_minmax(19rem,27rem)] lg:gap-8">
                    <section class="overflow-hidden rounded-[2.2rem] border border-slate-200/80 bg-linear-to-br {{ $panelGradient }} p-6 shadow-[0_34px_120px_-72px_rgba(15,23,42,0.45)] backdrop-blur sm:p-8 lg:p-10 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-3 py-1.5 text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase shadow-sm dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                                <span class="inline-flex h-2 w-2 rounded-full bg-[#ea5a47]"></span>
                                {{ __('errors.shared.eyebrow') }}
                            </div>
                            <div class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium {{ $statusChipClass }}">
                                {{ $badge }}
                            </div>
                        </div>

                        <div class="mt-8 max-w-3xl">
                            <p class="text-sm font-medium tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                {{ __('errors.shared.status', ['code' => $statusCode]) }}
                            </p>
                            <h1 class="mt-3 text-[2.4rem] leading-[0.95] font-semibold tracking-[-0.045em] text-slate-950 sm:text-[3rem] lg:text-[3.55rem] dark:text-slate-50">
                                {{ $title }}
                            </h1>
                            <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg dark:text-slate-300">
                                {{ $message }}
                            </p>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-500 dark:text-slate-400">
                                {{ $detail }}
                            </p>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-3">
                            @if ($showDashboardCta)
                                <a
                                    href="{{ $dashboardUrl }}"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#db4a37]"
                                >
                                    {{ __('errors.actions.dashboard') }}
                                </a>
                            @endif

                            @if ($showHomeCta)
                                <a
                                    href="{{ $homeUrl }}"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-100 dark:hover:border-slate-700"
                                >
                                    {{ __('errors.actions.home') }}
                                </a>
                            @endif

                            @if ($showReloadCta)
                                <button
                                    type="button"
                                    onclick="window.location.reload()"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-100 dark:hover:border-slate-700"
                                >
                                    {{ __('errors.actions.reload') }}
                                </button>
                            @endif
                        </div>

                        <div class="mt-10 grid gap-3 sm:grid-cols-3">
                            <article class="rounded-[1.6rem] border border-slate-200/80 bg-white/80 p-4 shadow-[0_16px_44px_-36px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950/70">
                                <p class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400">
                                    {{ __('errors.shared.status_card') }}
                                </p>
                                <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                    {{ $statusCode }}
                                </p>
                            </article>
                            <article class="rounded-[1.6rem] border border-slate-200/80 bg-white/80 p-4 shadow-[0_16px_44px_-36px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950/70">
                                <p class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400">
                                    {{ __('errors.shared.language_card') }}
                                </p>
                                <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                    {{ strtoupper($resolvedLocale) }}
                                </p>
                            </article>
                            <article class="rounded-[1.6rem] border border-slate-200/80 bg-white/80 p-4 shadow-[0_16px_44px_-36px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950/70">
                                <p class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400">
                                    {{ __('errors.shared.next_step_card') }}
                                </p>
                                <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                    {{ $nextStepCopy }}
                                </p>
                            </article>
                        </div>

                        <p class="mt-8 text-xs leading-6 text-slate-500 dark:text-slate-400">
                            {{ __('errors.shared.footer') }}
                        </p>
                    </section>

                    <aside class="overflow-hidden rounded-[2.35rem] border border-white/10 bg-linear-to-br {{ $visualGradient }} p-5 text-white shadow-[0_34px_120px_-72px_rgba(15,23,42,0.7)]">
                        <div class="rounded-[1.9rem] border border-white/10 bg-white/5 p-5 backdrop-blur">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[11px] font-semibold tracking-[0.18em] text-white/60 uppercase">
                                        {{ __('errors.shared.brand') }}
                                    </p>
                                    <p class="mt-2 text-sm leading-6 text-white/68">
                                        {{ $visualBody }}
                                    </p>
                                </div>
                                <div class="inline-flex rounded-full border border-white/12 bg-white/10 px-3 py-1 text-xs font-medium text-white/72">
                                    {{ __('errors.shared.status', ['code' => $statusCode]) }}
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-center">
                                <div class="relative">
                                    <div class="absolute inset-0 rounded-full bg-linear-to-br {{ $iconToneClass }} blur-2xl opacity-55"></div>
                                    <div class="relative flex h-28 w-28 items-center justify-center rounded-full border border-white/12 bg-white/10 backdrop-blur">
                                        @switch($icon)
                                            @case('shield')
                                                <svg viewBox="0 0 24 24" fill="none" class="h-12 w-12 text-white">
                                                    <path d="M12 3l7 3v5c0 4.2-2.6 8.1-7 10-4.4-1.9-7-5.8-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                    <path d="m9.2 12 1.9 1.9 3.7-4.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                @break
                                            @case('refresh')
                                                <svg viewBox="0 0 24 24" fill="none" class="h-12 w-12 text-white">
                                                    <path d="M20 5v5h-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M4 19v-5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M6.8 9A7 7 0 0 1 19 10M17.2 15A7 7 0 0 1 5 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                @break
                                            @case('pulse')
                                                <svg viewBox="0 0 24 24" fill="none" class="h-12 w-12 text-white">
                                                    <path d="M3 12h4l2-4 4 8 2-4h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                @break
                                            @case('spark')
                                                <svg viewBox="0 0 24 24" fill="none" class="h-12 w-12 text-white">
                                                    <path d="M12 3v4M12 17v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M3 12h4M17 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                    <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/>
                                                </svg>
                                                @break
                                            @case('wrench')
                                                <svg viewBox="0 0 24 24" fill="none" class="h-12 w-12 text-white">
                                                    <path d="m14.5 6.5 3 3-8.8 8.8a2.1 2.1 0 0 1-3 0l-.1-.1a2.1 2.1 0 0 1 0-3L14.5 6.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                    <path d="M20 4a4.5 4.5 0 0 1-5.8 5.8l-1.4-1.4A4.5 4.5 0 0 1 18.6 2L17 3.6 20.4 7 22 5.4A4.5 4.5 0 0 1 20 4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                </svg>
                                                @break
                                        @endswitch
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 rounded-[1.6rem] border border-white/10 bg-black/10 p-4">
                                <p class="text-[11px] font-semibold tracking-[0.16em] text-white/60 uppercase">
                                    {{ $badge }}
                                </p>
                                <p class="mt-3 text-xl leading-tight font-semibold tracking-tight text-white">
                                    {{ $visualTitle }}
                                </p>
                                <p class="mt-3 text-sm leading-7 text-white/72">
                                    {{ $visualBody }}
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </body>
</html>
