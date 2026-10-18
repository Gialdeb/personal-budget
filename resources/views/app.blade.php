<!DOCTYPE html>
@php
    use App\Support\Pwa\PwaManifestData;
    use Illuminate\Support\Facades\Vite;

    $requiresSessionVerification = ($page['props']['sessionVerificationRequired'] ?? false) === true;
    $pwaVersion = app(PwaManifestData::class)->version();
    $publicSeo = is_array($page['props']['publicSeo'] ?? null) ? $page['props']['publicSeo'] : null;
    $umamiEnabled = (bool) config('analytics.umami.enabled')
        && filled(config('analytics.umami.website_id'))
        && filled(config('analytics.umami.script_url'));
    $umamiDomains = config('analytics.umami.domains', []);
@endphp
<html @if($requiresSessionVerification) data-session-private data-session-unverified @endif lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="application-name" content="{{ config('app.name', 'Soamco Budget') }}">
        <meta name="soamco-asset-version" content="{{ inertia()->getVersion() }}">
        <meta name="soamco-asset-version-endpoint" content="{{ route('asset-version') }}">
        <meta name="theme-color" content="#ea5a47">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Soamco Budget') }}">
        <meta name="format-detection" content="telephone=no">
        <meta name="msapplication-TileColor" content="#ea5a47">
        <meta name="soamco-pwa-enabled" content="{{ app()->isLocal() && Vite::isRunningHot() ? 'false' : 'true' }}">
        <meta name="soamco-pwa-debug" content="{{ app()->isLocal() || config('app.debug') ? 'true' : 'false' }}">
        <meta name="soamco-push-debug" content="{{ app()->isLocal() || config('app.debug') ? 'true' : 'false' }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        {{--suppress CssUnusedSymbol --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            /*noinspection CssUnusedSymbol*/
            html.dark {
                background-color: oklch(0.145 0 0);
            }

            html[data-session-unverified] #app { visibility: hidden; }
            html[data-session-unverified] body > :not(#session-state):not(#soamco-startup):not(template) { display: none !important; }
            #session-state {position:fixed;inset:0;z-index:2147483646;display:grid;place-content:center;background:#faf9f7;color:#334155;text-align:center;font:16px system-ui;padding:24px;}
            html.dark #session-state {background:#0f172a;color:#e2e8f0;}
            #session-state[hidden] {display:none;}
            .startup-indicator { width: 24px; height: 24px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; opacity: 0; animation: startup-appear 0s 180ms forwards, startup-spin 900ms linear infinite; }
            #session-state { justify-items: center; gap: 16px; }
            #session-state p { margin: 0; }
            .startup-label { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
            @keyframes startup-appear { to { opacity: .45; } }
            @keyframes startup-spin { to { transform: rotate(360deg); } }
            @media (prefers-reduced-motion: reduce) {
                .startup-indicator { animation: none; opacity: .45; }
            }
            #session-state iframe {position:fixed;inset:0;width:100%;height:100%;border:0;}

            #soamco-startup {
                position: fixed;
                inset: 0;
                z-index: 2147483647;
                display: grid;
                place-content: center;
                justify-items: center;
                gap: 16px;
                padding: 24px;
                background: #faf9f7;
                color: #334155;
                font: 16px/1.5 system-ui, -apple-system, sans-serif;
                text-align: center;
            }
            html.dark #soamco-startup { background: #0f172a; color: #e2e8f0; }
            #soamco-startup [data-startup-brand] svg {width:64px;height:64px;}
            #soamco-startup img { width: 64px; height: 64px; border-radius: 16px; }
            #soamco-startup p { margin: 0; }
            #soamco-startup a { color: inherit; text-underline-offset: 4px; padding: 12px; }
            #soamco-startup [hidden] { display: none; }
        </style>

        <!--suppress HtmlUnknownAttribute -->
        <title inertia>{{ $publicSeo !== null ? $publicSeo['title'].' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}</title>
        @if ($publicSeo !== null)
            <meta name="description" content="{{ $publicSeo['description'] }}">
            <meta name="robots" content="{{ $publicSeo['robots'] }}">
            <meta property="og:title" content="{{ $publicSeo['title'] }}">
            <meta property="og:description" content="{{ $publicSeo['description'] }}">
            <meta property="og:type" content="{{ $publicSeo['og_type'] }}">
            <meta property="og:url" content="{{ $publicSeo['canonical_url'] }}">
            <meta property="og:locale" content="{{ $publicSeo['locale'] }}">
            <meta property="og:site_name" content="{{ config('app.name', 'Soamco Budget') }}">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="{{ $publicSeo['title'] }}">
            <meta name="twitter:description" content="{{ $publicSeo['description'] }}">
            <link rel="canonical" href="{{ $publicSeo['canonical_url'] }}">
            @foreach (($publicSeo['alternates'] ?? []) as $alternate)
                <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['url'] }}">
            @endforeach
            @foreach (($publicSeo['json_ld'] ?? []) as $schema)
                <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
            @endforeach
        @endif

        <link rel="icon" href="/favicon.ico?v={{ $pwaVersion }}" sizes="any">
        <link rel="icon" href="/favicon.svg?v={{ $pwaVersion }}" type="image/svg+xml">
        <link rel="mask-icon" href="/favicon.svg?v={{ $pwaVersion }}" color="#ea5a47">
        <link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/icon-152.png?v={{ $pwaVersion }}">
        <link rel="apple-touch-icon" sizes="167x167" href="/pwa/icons/icon-167.png?v={{ $pwaVersion }}">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v={{ $pwaVersion }}">
        <link rel="manifest" href="{{ route('pwa.manifest', ['v' => $pwaVersion]) }}" type="application/manifest+json">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&amp;display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&amp;display=swap" rel="stylesheet" /></noscript>

        @vite(['resources/js/app.ts'])
        @if ($umamiEnabled)
            <script
                defer
                src="{{ config('analytics.umami.script_url') }}"
                data-website-id="{{ config('analytics.umami.website_id') }}"
                data-auto-track="false"
                @if (! empty($umamiDomains))
                    data-domains="{{ implode(',', $umamiDomains) }}"
                @endif
                @if (config('analytics.umami.environment_tag'))
                    data-tag="{{ config('analytics.umami.environment_tag') }}"
                @endif
                @if (config('analytics.umami.respect_dnt', true))
                    data-do-not-track="true"
                @endif
            ></script>
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <div id="soamco-startup" role="status" aria-live="polite">
            <div data-startup-brand aria-label="Soamco Budget">{!! file_get_contents(public_path('pwa/icon-source.svg')) !!}</div>
            <div data-startup-loading>
                <span class="startup-indicator" style="display:block" aria-hidden="true"></span>
                <span class="startup-label">{{ app()->getLocale() === 'it' ? 'Caricamento…' : 'Loading…' }}</span>
            </div>
            <div data-startup-error hidden>
                <p>{{ app()->getLocale() === 'it' ? 'Il caricamento sta impiegando più tempo del previsto. Controlla la connessione e riprova.' : 'Loading is taking longer than expected. Check your connection and try again.' }}</p>
                <a href="{{ request()->getRequestUri() }}">{{ app()->getLocale() === 'it' ? 'Riprova' : 'Try again' }}</a>
            </div>
            <noscript>{{ app()->getLocale() === 'it' ? 'Abilita JavaScript per aprire Soamco.' : 'Enable JavaScript to open Soamco.' }}</noscript>
        </div>
        <script>
            (() => {
                const shell = document.getElementById('soamco-startup');
                performance.mark('soamco:shell');
                let mounted = false;
                const showFallback = () => {
                    if (mounted) return;
                    if (navigator.onLine === false) {
                        shell.querySelector('[data-startup-error] p').textContent = document.documentElement.lang.startsWith('it')
                            ? 'Connessione non disponibile. Controlla la rete e premi Riprova.'
                            : 'You are offline. Check your connection and retry.';
                    }
                    shell.querySelector('[data-startup-loading]').hidden = true;
                    shell.querySelector('[data-startup-error]').hidden = false;
                };
                const timeout = window.setTimeout(showFallback, 8000);
                const online = () => { if (!mounted && !shell.querySelector('[data-startup-error]').hidden) window.location.reload(); };
                const assetError = (event) => { if (event.target?.tagName === 'SCRIPT' || event.target?.tagName === 'LINK') showFallback(); };
                const ready = () => {
                    mounted = true;
                    window.clearTimeout(timeout);
                    window.removeEventListener('offline', showFallback);
                    window.removeEventListener('online', online);
                    window.removeEventListener('error', assetError, true);
                    window.removeEventListener('soamco:startup-error', showFallback);
                    shell.remove();
                };
                window.addEventListener('soamco:ready', ready, { once: true });
                window.addEventListener('soamco:startup-error', showFallback);
                window.addEventListener('offline', showFallback);
                window.addEventListener('online', online);
                window.addEventListener('error', assetError, true);
                if (navigator.onLine === false) showFallback();
            })();
        </script>
        <div id="session-state" role="status" @if(!$requiresSessionVerification) hidden @endif><span class="startup-indicator" aria-hidden="true"></span><span class="startup-label">{{ app()->getLocale() === 'it' ? 'Caricamento…' : 'Loading…' }}</span></div>
        <template id="neutral-offline-page">{!! file_get_contents(public_path('offline.html')) !!}</template>
        @inertia
    </body>
</html>
