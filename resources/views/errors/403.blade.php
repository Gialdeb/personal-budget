@include('errors.partials.error-page', [
    'statusCode' => 403,
    'translationKey' => '403',
    'icon' => 'shield',
    'showDashboardCta' => true,
    'showHomeCta' => true,
    'showReloadCta' => false,
    'statusChipClass' => 'border-[#d7def8] bg-[#edf2ff] text-[#4f5fd7] dark:border-[#39427a] dark:bg-[#1f2647] dark:text-[#cad4ff]',
    'panelGradient' => 'from-[#f7f9ff] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900',
    'visualGradient' => 'from-[#0f172a] via-[#1e293b] to-[#4338ca]',
    'iconToneClass' => 'from-[#6366f1] to-[#22c55e]',
])
