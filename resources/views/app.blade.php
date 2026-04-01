<!DOCTYPE html>
@php
    use App\Support\Pwa\PwaManifestData;
    use Illuminate\Support\Facades\Vite;

    $pwaVersion = app(PwaManifestData::class)->version();
    $umamiEnabled = (bool) config('analytics.umami.enabled')
        && filled(config('analytics.umami.website_id'))
        && filled(config('analytics.umami.script_url'));
    $umamiDomains = config('analytics.umami.domains', []);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{--suppress HtmlUnknownAttribute --}}
        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico?v={{ $pwaVersion }}" sizes="any">
        <link rel="icon" href="/favicon.svg?v={{ $pwaVersion }}" type="image/svg+xml">
        <link rel="mask-icon" href="/favicon.svg?v={{ $pwaVersion }}" color="#ea5a47">
        <link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/icon-152.png?v={{ $pwaVersion }}">
        <link rel="apple-touch-icon" sizes="167x167" href="/pwa/icons/icon-167.png?v={{ $pwaVersion }}">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v={{ $pwaVersion }}">
        <link rel="manifest" href="{{ route('pwa.manifest', ['v' => $pwaVersion]) }}" type="application/manifest+json">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

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
        @inertia
    </body>
</html>
