@include('errors.partials.error-page', [
    'statusCode' => 404,
    'translationKey' => '404',
    'icon' => 'spark',
    'showDashboardCta' => true,
    'showHomeCta' => true,
    'showReloadCta' => false,
    'statusChipClass' => 'border-[#d7e7f7] bg-[#eff7ff] text-[#2f71b8] dark:border-[#274767] dark:bg-[#132437] dark:text-[#a7d3ff]',
    'panelGradient' => 'from-[#f5fbff] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900',
    'visualGradient' => 'from-[#0f172a] via-[#1d4ed8] to-[#0ea5e9]',
    'iconToneClass' => 'from-[#60a5fa] to-[#22d3ee]',
])
