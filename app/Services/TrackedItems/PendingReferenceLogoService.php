<?php

namespace App\Services\TrackedItems;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PendingReferenceLogoService
{
    /** @return array{token: string, logo_url: string} */
    public function upload(User $actor, UploadedFile $file): array
    {
        $path = $file->store('pending-reference-logos/'.$actor->id, 'local');
        if ($path === false) {
            throw new \RuntimeException('Unable to store temporary logo.');
        }

        return ['token' => Crypt::encryptString(json_encode(['actor_id' => $actor->id, 'path' => $path, 'expires_at' => now()->addDay()->timestamp], JSON_THROW_ON_ERROR)),
            'logo_url' => 'data:'.$file->getMimeType().';base64,'.base64_encode($file->getContent())];
    }

    public function temporaryPath(User $actor, string $token, bool $mustExist = true): string
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->invalid();
        }
        if (($data['actor_id'] ?? null) !== $actor->id || ($data['expires_at'] ?? 0) <= now()->timestamp
            || ! str_starts_with($data['path'] ?? '', 'pending-reference-logos/'.$actor->id.'/')
            || ($mustExist && ! Storage::disk('local')->exists($data['path']))) {
            $this->invalid();
        }

        return $data['path'];
    }

    public function previewUpload(string $path): string
    {
        $bytes = Storage::disk('local')->get($path);

        return 'data:'.(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes).';base64,'.base64_encode($bytes);
    }

    public function discard(User $actor, string $token): void
    {
        Storage::disk('local')->delete($this->temporaryPath($actor, $token, mustExist: false));
    }

    public function brand(string $uuid): Brand
    {
        $brand = Brand::query()->published()->where('uuid', $uuid)->first();
        if ($brand === null || ! $brand->logo_path || ! Storage::disk('public')->exists($brand->logo_path)) {
            $this->invalid('logo_brand_uuid');
        }

        return $brand;
    }

    /** @param array<string, mixed> $draft */
    public function persist(User $actor, array $draft): ?string
    {
        $temporary = filled($draft['logo_upload_token'] ?? null) ? $this->temporaryPath($actor, $draft['logo_upload_token']) : null;
        $brand = filled($draft['logo_brand_uuid'] ?? null) ? $this->brand($draft['logo_brand_uuid']) : null;
        if ($temporary === null && $brand === null) {
            return null;
        }
        $bytes = $temporary !== null ? Storage::disk('local')->get($temporary) : Storage::disk('public')->get($brand->logo_path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = match ($mime) {
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
            'image/gif' => 'gif', 'image/x-icon', 'image/vnd.microsoft.icon' => 'ico', default => null
        };
        if ($extension === null) {
            $this->invalid();
        }
        $path = 'reference-logos/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('public')->put($path, $bytes)) {
            Storage::disk('public')->delete($path);
            throw new \RuntimeException('Unable to persist reference logo.');
        }
        $files = request()->attributes->get('pending_reference_logo_files', []);
        $files[] = ['path' => $path, 'temporary' => $temporary];
        request()->attributes->set('pending_reference_logo_files', $files);

        return $path;
    }

    public function finish(bool $committed): void
    {
        foreach (request()->attributes->get('pending_reference_logo_files', []) as $file) {
            if (! $committed) {
                Storage::disk('public')->delete($file['path']);
            } elseif ($file['temporary'] !== null) {
                Storage::disk('local')->delete($file['temporary']);
            }
        }
        request()->attributes->remove('pending_reference_logo_files');
    }

    public function prune(): void
    {
        foreach (Storage::disk('local')->allFiles('pending-reference-logos') as $path) {
            if (Storage::disk('local')->lastModified($path) <= now()->subDay()->timestamp) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function invalid(string $field = 'logo_upload_token'): never
    {
        throw ValidationException::withMessages([$field => 'Il logo non è più disponibile. Selezionalo nuovamente.']);
    }
}
