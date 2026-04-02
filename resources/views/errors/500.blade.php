@include('errors.partials.error-page', [
    'statusCode' => 500,
    'translationKey' => '500',
    'icon' => 'spark',
    'showDashboardCta' => true,
    'showHomeCta' => true,
    'showReloadCta' => false,
    'statusChipClass' => 'border-[#e1d8f6] bg-[#f6f1ff] text-[#7653c8] dark:border-[#4a3772] dark:bg-[#231733] dark:text-[#d5c3ff]',
    'panelGradient' => 'from-[#faf7ff] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900',
    'visualGradient' => 'from-[#111827] via-[#312e81] to-[#7c3aed]',
    'iconToneClass' => 'from-[#8b5cf6] to-[#22d3ee]',
])
