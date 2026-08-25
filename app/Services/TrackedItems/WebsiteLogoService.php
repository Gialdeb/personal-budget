<?php

namespace App\Services\TrackedItems;

use App\Models\WebsiteIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WebsiteLogoService
{
    protected const MAX_DOCUMENT_BYTES = 524_288;

    /**
     * Remote assets may be much larger than a logo needs to be. Keep the download
     * bounded, then generate a small, display-ready derivative locally.
     */
    protected const MAX_SOURCE_LOGO_BYTES = 8_388_608;

    protected const MAX_STORED_LOGO_BYTES = 524_288;

    protected const MAX_SOURCE_LOGO_PIXELS = 20_000_000;

    protected const MAX_LOGO_WIDTH = 512;

    protected const MAX_LOGO_HEIGHT = 256;

    protected const MAX_REDIRECTS = 3;

    protected const MAX_FETCH_QUERY_BYTES = 2_048;

    /** @var array<string, string> */
    protected const MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    public function resolve(string $input, bool $retryUnavailable = false): WebsiteIdentity
    {
        $normalizedUrl = $this->normalizeUrl($input);
        $domain = $this->identityDomain($normalizedUrl);
        $identity = WebsiteIdentity::query()->firstOrCreate(
            ['domain' => $domain],
            [
                'canonical_url' => $this->canonicalRoot($normalizedUrl),
                'status' => 'pending',
            ],
        );

        $identity->forceFill([
            'canonical_url' => $this->canonicalRoot($normalizedUrl),
        ])->save();

        if (
            $this->shouldUseCachedResult($identity)
            && (! $retryUnavailable || $identity->hasStoredLogo())
        ) {
            return $identity;
        }

        try {
            $logo = $this->discoverLogo($normalizedUrl);

            if ($logo === null) {
                Log::notice('No tracked item logo could be discovered.', [
                    'domain' => $domain,
                ]);

                return $this->markUnavailable($identity, 'not_found');
            }

            $extension = self::MIME_EXTENSIONS[$logo['mime_type']];
            $path = sprintf(
                'tracked-item-logos/%s.%s',
                hash('sha256', $logo['bytes']),
                $extension,
            );

            if (! Storage::disk('public')->put($path, $logo['bytes'])) {
                throw new \RuntimeException('Unable to persist the tracked item logo.');
            }

            $identity->forceFill([
                'logo_path' => $path,
                'logo_mime_type' => $logo['mime_type'],
                'logo_source_url' => $logo['source_url'],
                'status' => 'ready',
                'fetched_at' => now(),
                'retry_after' => now()->addDays(30),
            ])->save();

            return $identity->refresh();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Tracked item logo retrieval failed.', [
                'domain' => $domain,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->markUnavailable($identity, 'failed');
        }
    }

    public function normalizeUrl(string $input): string
    {
        $value = trim($input);

        if ($value === '') {
            throw $this->invalidUrlException();
        }

        if (str_contains($value, '://') && ! preg_match('~^https?://~i', $value)) {
            throw $this->invalidUrlException();
        }

        if (! preg_match('~^https?://~i', $value)) {
            $value = 'https://'.$value;
        } elseif (preg_match('~^http://~i', $value)) {
            $value = preg_replace('~^http://~i', 'https://', $value) ?? $value;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (
            $scheme !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || ($port !== null && $port !== 443)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || $this->isBlockedHostname($host)
        ) {
            throw $this->invalidUrlException();
        }

        $path = (string) ($parts['path'] ?? '');
        $portSuffix = $port === 443 ? ':443' : '';

        return sprintf('https://%s%s%s', $host, $portSuffix, $path !== '' ? $path : '/');
    }

    /**
     * @return array{bytes: string, mime_type: string, source_url: string}|null
     */
    protected function discoverLogo(string $websiteUrl): ?array
    {
        try {
            $document = $this->fetchFollowingRedirects($websiteUrl, self::MAX_DOCUMENT_BYTES);
        } catch (Throwable) {
            // A large homepage must not prevent the standard favicon fallback.
            $document = null;
        }

        $finalUrl = $document['final_url'] ?? $websiteUrl;
        $candidates = [];

        if ($document !== null && $document['bytes'] !== '') {
            $candidates = array_slice($this->iconCandidates($document['bytes'], $finalUrl), 0, 4);
        }

        $domain = $this->identityDomain($websiteUrl);
        $candidates = [
            ...$candidates,
            $this->resolveUrl('/favicon.ico', $finalUrl),
            $this->resolveUrl('/apple-touch-icon.png', $finalUrl),
            $this->resolveUrl('/apple-touch-icon-precomposed.png', $finalUrl),
            $this->resolveUrl('/favicon.png', $finalUrl),
            sprintf(
                'https://www.google.com/s2/favicons?domain_url=%s&sz=256',
                rawurlencode('https://'.$domain),
            ),
            sprintf('https://icons.duckduckgo.com/ip3/%s.ico', rawurlencode($domain)),
        ];
        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $candidate) {
            try {
                $response = $this->fetchFollowingRedirects($candidate, self::MAX_SOURCE_LOGO_BYTES);

                if ($response === null || $response['bytes'] === '') {
                    continue;
                }

                $mimeType = $this->detectAllowedMimeType($response['bytes']);

                if ($mimeType === null) {
                    continue;
                }

                $optimizedLogo = $this->optimizeLogo($response['bytes'], $mimeType);

                if ($optimizedLogo === null) {
                    continue;
                }

                return [
                    'bytes' => $optimizedLogo['bytes'],
                    'mime_type' => $optimizedLogo['mime_type'],
                    'source_url' => $response['final_url'],
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array{bytes: string, final_url: string}|null
     */
    protected function fetchFollowingRedirects(string $url, int $maximumBytes): ?array
    {
        $currentUrl = $this->normalizeFetchUrl($url);

        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $response = $this->request($currentUrl);

            if ($response->redirect()) {
                $location = $response->header('Location');

                if (! is_string($location) || $location === '') {
                    return null;
                }

                $currentUrl = $this->normalizeFetchUrl($this->resolveUrl($location, $currentUrl));

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            return [
                'bytes' => $this->readResponseBytes($response, $maximumBytes),
                'final_url' => $currentUrl,
            ];
        }

        return null;
    }

    protected function request(string $url): Response
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $addresses = $this->publicAddressesForHost($host);
        $request = Http::connectTimeout(2)
            ->timeout(6)
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,image/avif,image/webp,image/png,image/jpeg,image/gif,image/x-icon,*/*;q=0.5',
                'User-Agent' => 'Mozilla/5.0 (compatible; SoamcoBudgetLogoFetcher/1.0; +https://soamco.it)',
            ])
            ->withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ]);

        if (! defined('CURLOPT_RESOLVE')) {
            return $request->get($url);
        }

        $lastException = null;

        $lastAddressKey = array_key_last($addresses);

        foreach ($addresses as $key => $address) {
            try {
                $response = $this->pinRequestAddress($request, $host, $port, $address)->get($url);

                if ($this->shouldRetryAddress($response) && $key !== $lastAddressKey) {
                    continue;
                }

                return $response;
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? $this->unreachableUrlException();
    }

    protected function pinRequestAddress(PendingRequest $request, string $host, int $port, string $address): PendingRequest
    {
        $curlOptions = [
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, $address)],
        ];

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        return $request->withOptions(['curl' => $curlOptions]);
    }

    protected function shouldRetryAddress(Response $response): bool
    {
        return in_array($response->status(), [403, 408, 429, 500, 502, 503, 504], true);
    }

    /**
     * @return array<int, string>
     */
    protected function publicAddressesForHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $addresses = collect($records)
                ->flatMap(fn (array $record): array => array_values(array_filter([
                    $record['ip'] ?? null,
                    $record['ipv6'] ?? null,
                ], fn ($address): bool => is_string($address))))
                ->values()
                ->all();
        }

        if ($addresses === []) {
            throw $this->unreachableUrlException();
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw $this->invalidUrlException();
            }
        }

        return array_values(array_unique(array_map('strval', $addresses)));
    }

    protected function readResponseBytes(Response $response, int $maximumBytes): string
    {
        $contentLength = (int) ($response->header('Content-Length') ?? 0);

        if ($contentLength > $maximumBytes) {
            throw $this->logoTooLargeException();
        }

        $stream = $response->toPsrResponse()->getBody();
        $bytes = '';

        while (! $stream->eof()) {
            $remaining = $maximumBytes + 1 - strlen($bytes);

            if ($remaining <= 0) {
                throw $this->logoTooLargeException();
            }

            $bytes .= $stream->read(min(65_536, $remaining));
        }

        if (strlen($bytes) > $maximumBytes) {
            throw $this->logoTooLargeException();
        }

        return $bytes;
    }

    protected function normalizeFetchUrl(string $url): string
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '' || strlen($query) > self::MAX_FETCH_QUERY_BYTES) {
            return $normalizedUrl;
        }

        return $normalizedUrl.'?'.$query;
    }

    /** @return array<int, string> */
    protected function iconCandidates(string $html, string $baseUrl): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $candidates = [];

        foreach ($document->getElementsByTagName('link') as $link) {
            $relations = preg_split('/\s+/', strtolower($link->getAttribute('rel'))) ?: [];

            if (! in_array('icon', $relations, true) && ! in_array('apple-touch-icon', $relations, true)) {
                continue;
            }

            $href = trim($link->getAttribute('href'));

            if ($href === '' || Str::startsWith($href, ['data:', 'javascript:'])) {
                continue;
            }

            $priority = in_array('apple-touch-icon', $relations, true) ? 200 : 100;
            $sizes = $link->getAttribute('sizes');

            if (preg_match('/(\d+)x\d+/', $sizes, $matches)) {
                $priority += min((int) $matches[1], 512);
            }

            $candidates[] = [
                'url' => $this->resolveUrl(html_entity_decode($href, ENT_QUOTES | ENT_HTML5), $baseUrl),
                'priority' => $priority,
            ];
        }

        return collect($candidates)
            ->sortByDesc('priority')
            ->pluck('url')
            ->filter(fn ($url): bool => is_string($url) && $url !== '')
            ->values()
            ->all();
    }

    protected function resolveUrl(string $candidate, string $baseUrl): string
    {
        if (preg_match('~^https?://~i', $candidate)) {
            return $candidate;
        }

        $base = parse_url($baseUrl);
        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) ($base['host'] ?? '');
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (Str::startsWith($candidate, '//')) {
            return $scheme.':'.$candidate;
        }

        if (Str::startsWith($candidate, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $candidate);
        }

        $basePath = (string) ($base['path'] ?? '/');
        $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');

        $path = implode('/', array_filter([
            ltrim($directory, '/'),
            ltrim($candidate, '/'),
        ], fn (string $segment): bool => $segment !== ''));

        return sprintf('%s://%s%s/%s', $scheme, $host, $port, $path);
    }

    protected function detectAllowedMimeType(string $bytes): ?string
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return is_string($mimeType) && array_key_exists($mimeType, self::MIME_EXTENSIONS)
            ? $mimeType
            : null;
    }

    /**
     * Convert an external image into a compact, predictable asset for every
     * viewport. The original is intentionally never persisted.
     *
     * @return array{bytes: string, mime_type: string}|null
     */
    protected function optimizeLogo(string $bytes, string $mimeType): ?array
    {
        if (strlen($bytes) > self::MAX_SOURCE_LOGO_BYTES) {
            return null;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return strlen($bytes) <= self::MAX_STORED_LOGO_BYTES
                ? ['bytes' => $bytes, 'mime_type' => $mimeType]
                : null;
        }

        $size = @getimagesizefromstring($bytes);

        if (
            $size === false
            || $size[0] < 1
            || $size[1] < 1
            || $size[0] * $size[1] > self::MAX_SOURCE_LOGO_PIXELS
        ) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return in_array($mimeType, ['image/x-icon', 'image/vnd.microsoft.icon'], true)
                && strlen($bytes) <= self::MAX_STORED_LOGO_BYTES
                && $size[0] <= self::MAX_LOGO_WIDTH
                && $size[1] <= self::MAX_LOGO_HEIGHT
                    ? ['bytes' => $bytes, 'mime_type' => $mimeType]
                    : null;
        }

        $scale = min(
            self::MAX_LOGO_WIDTH / $size[0],
            self::MAX_LOGO_HEIGHT / $size[1],
            1,
        );
        $targetWidth = max(1, (int) round($size[0] * $scale));
        $targetHeight = max(1, (int) round($size[1] * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $size[0], $size[1]);
        imagedestroy($source);

        try {
            if (function_exists('imagewebp')) {
                foreach ([88, 80, 72, 64] as $quality) {
                    ob_start();
                    $encoded = imagewebp($target, null, $quality);
                    $optimizedBytes = (string) ob_get_clean();

                    if ($encoded && strlen($optimizedBytes) <= self::MAX_STORED_LOGO_BYTES) {
                        return ['bytes' => $optimizedBytes, 'mime_type' => 'image/webp'];
                    }
                }
            }

            ob_start();
            $encoded = imagepng($target, null, 9);
            $optimizedBytes = (string) ob_get_clean();

            return $encoded && strlen($optimizedBytes) <= self::MAX_STORED_LOGO_BYTES
                ? ['bytes' => $optimizedBytes, 'mime_type' => 'image/png']
                : null;
        } finally {
            imagedestroy($target);
        }
    }

    protected function identityDomain(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
    }

    protected function canonicalRoot(string $url): string
    {
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        return sprintf('%s://%s%s', $scheme, $host, $port !== null ? ':'.$port : '');
    }

    protected function shouldUseCachedResult(WebsiteIdentity $identity): bool
    {
        if ($identity->hasStoredLogo() && $identity->fetched_at?->isAfter(now()->subDays(30))) {
            return true;
        }

        return ! $identity->hasStoredLogo()
            && $identity->status !== 'ready'
            && $identity->retry_after?->isFuture() === true;
    }

    protected function markUnavailable(WebsiteIdentity $identity, string $status): WebsiteIdentity
    {
        if ($identity->hasStoredLogo()) {
            $identity->forceFill(['retry_after' => now()->addDay()])->save();

            return $identity->refresh();
        }

        $identity->forceFill([
            'status' => $status,
            'fetched_at' => CarbonImmutable::now(),
            'retry_after' => now()->addDay(),
        ])->save();

        return $identity->refresh();
    }

    protected function isBlockedHostname(string $host): bool
    {
        return $host === 'localhost'
            || Str::endsWith($host, ['.localhost', '.local', '.internal', '.test'])
            || filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    protected function invalidUrlException(): ValidationException
    {
        return ValidationException::withMessages([
            'website_url' => __('tracked_items.logo.validation.invalid_url'),
        ]);
    }

    protected function unreachableUrlException(): ValidationException
    {
        return ValidationException::withMessages([
            'website_url' => __('tracked_items.logo.validation.unreachable'),
        ]);
    }

    protected function logoTooLargeException(): ValidationException
    {
        return ValidationException::withMessages([
            'website_url' => __('tracked_items.logo.validation.too_large'),
        ]);
    }
}
