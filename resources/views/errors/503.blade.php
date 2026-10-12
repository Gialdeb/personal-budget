<!doctype html><html lang="{{ str_starts_with(request()->getPreferredLanguage(['it', 'en']) ?? 'it', 'it') ? 'it' : 'en' }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="10"><title>Soamco</title><style>
*{box-sizing:border-box}html{color-scheme:light dark}
body{margin:0;min-height:100dvh;display:grid;place-items:center;padding:24px;background:radial-gradient(ellipse at 50% 42%,#f8e8e1 0,transparent 55%),#faf9f7;color:#172033;font:16px/1.6 system-ui,-apple-system,sans-serif;text-align:center}
main{width:100%;max-width:440px;padding:32px 20px}
.brand{display:flex;flex-direction:column;align-items:center;gap:14px}
.brand svg{width:72px;height:72px;filter:drop-shadow(0 8px 18px #e85a4f25)}
strong{font-size:14px;letter-spacing:.06em;color:#64748b}
h1{font-size:clamp(24px,5vw,30px);line-height:1.25;letter-spacing:-.035em;margin:28px 0 14px;font-weight:650}
p{font-size:15px;line-height:1.7;color:#64748b;max-width:360px;margin:0 auto}
.activity{display:flex;justify-content:center;gap:7px;margin-top:28px}
.activity span{width:5px;height:5px;background:#ea5a47;border-radius:50%;animation:breathe 1.5s ease-in-out infinite}
.activity span:nth-child(2){animation-delay:.2s}.activity span:nth-child(3){animation-delay:.4s}
@keyframes breathe{0%,80%,100%{opacity:.25;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}
@media(prefers-reduced-motion:reduce){.activity span{animation:none;opacity:.65}}
@media(prefers-color-scheme:dark){body{background:radial-gradient(ellipse at 50% 42%,#253047 0,transparent 55%),#0f172a;color:#f8fafc}strong{color:#a3afc2}p{color:#b5c0d1}}
</style></head><body><main><div class="brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Soamco Budget">
  <defs>
    <linearGradient id="bg" x1="8" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#F97360"/>
      <stop offset="1" stop-color="#E85A4F"/>
    </linearGradient>
  </defs>
  <rect x="6" y="6" width="52" height="52" rx="15" fill="url(#bg)"/>
  <g fill="none" stroke="#FFFFFF" stroke-linecap="round" stroke-linejoin="round">
    <path d="M18 36L30 24L39 32L47 17" stroke-width="4.6"/>
    <path d="M41 17H47V23" stroke-width="4.6"/>
    <path d="M20 44V36" stroke-width="4.8"/>
    <path d="M30 44V28" stroke-width="4.8"/>
    <path d="M40 44V34" stroke-width="4.8"/>
  </g>
</svg>
<strong>Soamco Budget</strong></div>@if(str_starts_with(request()->getPreferredLanguage(['it', 'en']) ?? 'it', 'it'))<h1>Stiamo aggiornando Soamco</h1><p>Torneremo disponibili tra poco. La pagina si aggiornerà automaticamente.</p>@else<h1>We are updating Soamco</h1><p>We will be back shortly. This page will refresh automatically.</p>@endif<div class="activity" aria-hidden="true"><span></span><span></span><span></span></div></main></body></html>