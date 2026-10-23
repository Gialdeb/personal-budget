<?php

namespace App\Services\Brands;

use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\WebsiteLogoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BrandLogoService
{
    /** @return array{logo_path: string, logo_mime_type: string} */
    public function upload(UploadedFile $file): array
    {
        return $this->store($file->get(), $file->getMimeType());
    }

    /** @return array{logo_path: string, logo_mime_type: string} */
    public function fromWebsite(string $domain): array
    {
        $preview = app(WebsiteLogoService::class)->preview($domain);
        $identity = WebsiteIdentity::query()->where('domain', $preview['domain'])->first();
        if ($identity?->hasStoredLogo()) {
            return ['logo_path' => $identity->logo_path, 'logo_mime_type' => $identity->logo_mime_type];
        }
        if (is_string($preview['logo_url']) && preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#s', $preview['logo_url'], $match)) {
            $bytes = base64_decode($match[2], true);
            if ($bytes !== false) {
                return $this->store($bytes, $match[1]);
            }
        }
        throw ValidationException::withMessages(['logo' => __('form_errors.no_logo_is_available_from_this_website_you_can_upload_one_manually')]);
    }

    /** @return array{logo_path: string, logo_mime_type: string} */
    protected function store(string $bytes, string $mime): array
    {
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
        if (! isset($extensions[$mime]) || strlen($bytes) > 8_388_608) {
            throw ValidationException::withMessages(['logo' => __('form_errors.invalid_logo_format_or_size')]);
        }
        $path = 'tracked-item-logos/'.hash('sha256', $bytes).'.'.$extensions[$mime];
        if (! Storage::disk('public')->exists($path) && ! Storage::disk('public')->put($path, $bytes)) {
            throw ValidationException::withMessages(['logo' => __('form_errors.unable_to_save_the_logo_please_try_again')]);
        }

        return ['logo_path' => $path, 'logo_mime_type' => $mime];
    }
}
