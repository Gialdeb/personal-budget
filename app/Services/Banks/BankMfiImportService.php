<?php

namespace App\Services\Banks;

use App\Models\Bank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BankMfiImportService
{
    /**
     * @var list<string>
     */
    private const HEADERS = [
        'RIAD_CODE',
        'LEI',
        'COUNTRY_OF_REGISTRATION',
        'NAME',
        'BOX',
        'ADDRESS',
        'POSTAL',
        'CITY',
        'CATEGORY',
        'HEAD_COUNTRY_OF_REGISTRATION',
        'HEAD_NAME',
        'HEAD_RIAD_CODE',
        'HEAD_LEI',
        'REPORT',
    ];

    /**
     * @return array{read:int, imported:int, updated:int, skipped:int, errors:int}
     */
    public function importFromPath(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        $encoding = mb_detect_encoding($content, ['UTF-16', 'UTF-8', 'UTF-16LE', 'UTF-16BE'], true) ?: 'UTF-8';
        $utf8Content = mb_convert_encoding($content, 'UTF-8', $encoding);
        $rows = preg_split("/\r\n|\n|\r/", $utf8Content) ?: [];
        $rows = array_values(array_filter($rows, fn (string $row): bool => trim($row) !== ''));

        if ($rows === []) {
            throw new RuntimeException('The dataset is empty.');
        }

        $header = array_map(
            static fn (string $value): string => trim(str_replace("\u{FEFF}", '', $value)),
            str_getcsv(array_shift($rows), "\t", '"', '')
        );

        if ($header !== self::HEADERS) {
            throw new RuntimeException('Unexpected MFI dataset header.');
        }

        $summary = [
            'read' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        DB::transaction(function () use ($rows, &$summary): void {
            foreach ($rows as $row) {
                $summary['read']++;

                try {
                    $columns = str_getcsv($row, "\t", '"', '');

                    if (count($columns) !== count(self::HEADERS)) {
                        $summary['errors']++;

                        continue;
                    }

                    $normalized = $this->normalizeRecord(array_combine(self::HEADERS, $columns) ?: []);

                    if ($normalized === null) {
                        $summary['skipped']++;

                        continue;
                    }

                    $existingBank = $this->resolveExistingBank($normalized);
                    $normalized['slug'] = $this->resolveStableSlug($normalized, $existingBank);

                    if ($existingBank === null) {
                        Bank::query()->create($normalized);
                        $summary['imported']++;

                        continue;
                    }

                    $existingBank->fill($normalized);

                    if (! $existingBank->isDirty()) {
                        $summary['skipped']++;

                        continue;
                    }

                    $existingBank->save();
                    $summary['updated']++;
                } catch (\Throwable) {
                    $summary['errors']++;
                }
            }
        });

        return $summary;
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>|null
     */
    public function normalizeRecord(array $record): ?array
    {
        $name = $this->clean($record['NAME'] ?? null);
        $countryCode = $this->normalizeCountryCode($record['COUNTRY_OF_REGISTRATION'] ?? null);

        if ($name === null || $countryCode === null) {
            return null;
        }

        return [
            'name' => $name,
            'slug' => $this->baseSlug($name),
            'country_code' => $countryCode,
            'riad_code' => $this->clean($record['RIAD_CODE'] ?? null),
            'lei' => $this->clean($record['LEI'] ?? null),
            'address' => $this->combineAddress($record),
            'postal_code' => $this->clean($record['POSTAL'] ?? null),
            'city' => $this->clean($record['CITY'] ?? null),
            'category' => $this->clean($record['CATEGORY'] ?? null),
            'head_country_code' => $this->normalizeCountryCode($record['HEAD_COUNTRY_OF_REGISTRATION'] ?? null),
            'head_name' => $this->clean($record['HEAD_NAME'] ?? null),
            'head_riad_code' => $this->clean($record['HEAD_RIAD_CODE'] ?? null),
            'head_lei' => $this->clean($record['HEAD_LEI'] ?? null),
            'report_label' => $this->clean($record['REPORT'] ?? null),
            'logo_path' => null,
            'logo_url' => null,
            'sort_order' => null,
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function resolveExistingBank(array $record): ?Bank
    {
        $query = Bank::query();

        if (filled($record['riad_code'])) {
            return $query->where('riad_code', $record['riad_code'])->first();
        }

        return $query
            ->where('country_code', $record['country_code'])
            ->where('slug', $record['slug'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function resolveStableSlug(array $record, ?Bank $existingBank = null): string
    {
        $baseSlug = $this->baseSlug((string) $record['name']);

        if ($existingBank !== null) {
            return $existingBank->slug;
        }

        $collisionExists = Bank::query()
            ->where('country_code', $record['country_code'])
            ->where('slug', $baseSlug)
            ->when(
                filled($record['riad_code']),
                fn ($query) => $query->where('riad_code', '!=', $record['riad_code'])
            )
            ->exists();

        if (! $collisionExists) {
            return $baseSlug;
        }

        $suffix = filled($record['riad_code'])
            ? Str::lower(Str::substr((string) $record['riad_code'], -12))
            : Str::lower(Str::substr(sha1((string) $record['name'].'|'.(string) $record['country_code']), 0, 12));

        return Str::limit("{$baseSlug}-{$suffix}", 150, '');
    }

    private function baseSlug(string $name): string
    {
        return Str::limit(Str::slug($name), 150, '');
    }

    /**
     * @param  array<string, string>  $record
     */
    private function combineAddress(array $record): ?string
    {
        return collect([
            $this->clean($record['BOX'] ?? null),
            $this->clean($record['ADDRESS'] ?? null),
        ])->filter()->implode(', ') ?: null;
    }

    private function normalizeCountryCode(?string $value): ?string
    {
        $cleaned = $this->clean($value);

        if ($cleaned === null) {
            return null;
        }

        return Str::upper(Str::substr($cleaned, 0, 2));
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $cleaned !== '' ? $cleaned : null;
    }
}
