@include('errors.partials.error-page', [
    'statusCode' => 503,
    'translationKey' => '503',
    'icon' => 'wrench',
    'showDashboardCta' => false,
    'showHomeCta' => false,
    'showReloadCta' => true,
    'statusChipClass' => 'border-[#cfe8e1] bg-[#eefaf6] text-[#0f7a5c] dark:border-[#275847] dark:bg-[#0f241d] dark:text-[#9ce9cc]',
    'panelGradient' => 'from-[#f4fffb] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900',
    'visualGradient' => 'from-[#0f172a] via-[#064e3b] to-[#0f766e]',
    'iconToneClass' => 'from-[#10b981] to-[#38bdf8]',
])
