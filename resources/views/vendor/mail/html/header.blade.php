@props(['url', 'tagline' => null, 'logoUrl' => null])
@php
    $resolvedLogoUrl = $logoUrl ?? url('apple-touch-icon.png');
    $logoPath = public_path('apple-touch-icon.png');

    if (is_file($logoPath) && is_readable($logoPath)) {
        $mimeType = function_exists('mime_content_type') ? mime_content_type($logoPath) : 'image/png';
        $logoBytes = file_get_contents($logoPath);

        if ($mimeType && $logoBytes !== false) {
            $resolvedLogoUrl = 'data:'.$mimeType.';base64,'.base64_encode($logoBytes);
        }
    }
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-link">
<span class="brand-mark" aria-hidden="true">
<img src="{{ $resolvedLogoUrl }}" class="brand-logo" alt="{{ config('app.name') }} logo">
</span>
<span class="brand-copy">
<span class="brand-name">{{ config('app.name') }}</span>
<span class="brand-tagline">{{ $tagline }}</span>
</span>
</a>
</td>
</tr>
