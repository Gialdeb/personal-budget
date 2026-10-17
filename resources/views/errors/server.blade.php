<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · Soamco</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; background: #fffdfb; color: #0f172a; }
        body { margin: 0; min-height: 100svh; display: grid; place-items: center; }
        main { box-sizing: border-box; width: min(92%, 32rem); margin: 1.5rem; padding: 2rem; border: 1px solid #e2e8f0; border-radius: 1.5rem; background: #fff; box-shadow: 0 16px 48px -24px #33415550; }
        .brand { color: #c85d48; font-weight: 700; }
        h1 { font-size: clamp(1.5rem, 5vw, 2rem); line-height: 1.2; }
        p { line-height: 1.6; }
        .code { color: #64748b; font-size: .875rem; }
        nav { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
        button, a { font: inherit; font-weight: 600; padding: .75rem 1rem; min-height: 44px; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: .75rem; background: transparent; color: inherit; cursor: pointer; text-decoration: none; }
        button:first-child { background: #c85d48; color: white; border-color: #c85d48; }
        @media (prefers-color-scheme: dark) { :root { background: #020617; color: #f8fafc; } main { background: #0f172a; border-color: #334155; } button, a { border-color: #334155; } .code { color: #94a3b8; } }
    </style>
</head>
<body>
    <main>
        <span class="brand">Soamco</span>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <nav aria-label="{{ $title }}">
            <button type="button" onclick="window.location.reload()">{{ $retryLabel }}</button>
            <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js($dashboardUrl))">{{ $backLabel }}</button>
            <a href="{{ $dashboardUrl }}">{{ $dashboardLabel }}</a>
        </nav>
    </main>
</body>
</html>
