@include('errors.partials.error-page', [
    'statusCode' => 419,
    'translationKey' => '419',
    'icon' => 'refresh',
    'showDashboardCta' => true,
    'showHomeCta' => true,
    'showReloadCta' => true,
    'statusChipClass' => 'border-[#f4d8b5] bg-[#fff4e5] text-[#b96f1f] dark:border-[#6a4b21] dark:bg-[#352514] dark:text-[#ffd59a]',
    'panelGradient' => 'from-[#fff9f1] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900',
    'visualGradient' => 'from-[#111827] via-[#4b5563] to-[#d97706]',
    'iconToneClass' => 'from-[#f59e0b] to-[#fb7185]',
])
