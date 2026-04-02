@include('errors.partials.error-page', [
    'statusCode' => 429,
    'translationKey' => '429',
    'icon' => 'pulse',
    'showDashboardCta' => true,
    'showHomeCta' => true,
    'showReloadCta' => true,
    'statusChipClass' => 'border-[#f6d6d9] bg-[#fff0f1] text-[#c25163] dark:border-[#6f2b36] dark:bg-[#31151a] dark:text-[#ffb2c0]',
    'panelGradient' => 'from-[#fff5f6] via-white to-[#fffdfb] dark:from-slate-950 dark:via-slate-950 dark:to-slate-900',
    'visualGradient' => 'from-[#111827] via-[#3f3f46] to-[#be123c]',
    'iconToneClass' => 'from-[#fb7185] to-[#f59e0b]',
])
