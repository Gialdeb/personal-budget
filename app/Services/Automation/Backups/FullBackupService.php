<?php

namespace App\Services\Automation\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use JsonException;
use ZipArchive;

class FullBackupService
{
    public const ARCHIVE_PREFIX = 'full-backup-';

    public function run(): array
    {
        $this->ensureZipArchiveAvailable();

        $startedAt = microtime(true);
        $disk = Storage::disk(config('automation.backups.disk', 'local'));
        $timestamp = now()->format('Ymd_His');
        $relativePath = $this->directory().'/'.self::ARCHIVE_PREFIX.$timestamp.'.zip';

        $disk->makeDirectory(dirname($relativePath));

        $absolutePath = $disk->path($relativePath);

        $zip = new ZipArchive;
        $status = $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($status !== true) {
            throw new \RuntimeException('Unable to create full backup archive.');
        }

        try {
            $database = $this->exportDatabase();

            $manifest = [
                'type' => 'full_backup',
                'artifact_classification' => 'application_snapshot_archive',
                'restore_capability' => [
                    'is_automated_restore_available' => false,
                    'is_end_to_end_restorable' => false,
                    'level' => 'manual_rebuild_required',
                    'notes' => [
                        'The archive contains JSON table exports and selected application files.',
                        'No automated restore command is implemented for full environment rebuild.',
                        'Use this archive as an application snapshot for manual recovery or future restore tooling.',
                    ],
                ],
                'environment' => app()->environment(),
                'app_url' => config('app.url'),
                'created_at' => now()->toIso8601String(),
                'database' => [
                    'table_count' => count($database),
                    'tables' => array_keys($database),
                    'format' => 'json-per-table',
                ],
                'included_directories' => [
                    'local_disk_root' => $disk->path(''),
                    'public_disk_root' => Storage::disk('public')->path(''),
                ],
                'included_assets' => [
                    'public/apple-touch-icon.png',
                    'public/favicon.svg',
                ],
            ];

            $zip->addFromString('manifest.json', $this->encode($manifest));

            foreach ($database as $table => $rows) {
                $zip->addFromString("database/{$table}.json", $this->encode($rows));
            }

            $this->addDiskDirectory($zip, 'local', '', 'storage/local', [
                $this->directory(),
                trim((string) config('automation.backups.user.directory', 'backups/users'), '/'),
            ]);
            $this->addDiskDirectory($zip, 'public', '', 'storage/public');
            $this->addPublicAsset($zip, 'apple-touch-icon.png', 'assets/apple-touch-icon.png');
            $this->addPublicAsset($zip, 'favicon.svg', 'assets/favicon.svg');
        } finally {
            $zip->close();
        }

        $sizeBytes = is_file($absolutePath) ? filesize($absolutePath) ?: 0 : 0;
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'summary' => __('automation.backups.full_generated'),
            'path' => $relativePath,
            'absolute_path' => $absolutePath,
            'size_bytes' => $sizeBytes,
            'size_human' => Number::fileSize($sizeBytes),
            'duration_ms' => $durationMs,
            'duration_human' => $this->formatDuration($durationMs),
            'table_count' => count($database),
            'restore_capability' => 'manual_rebuild_required',
        ];
    }

    public function directory(): string
    {
        return trim((string) config('automation.backups.full.directory', 'backups/full'), '/');
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function exportDatabase(): array
    {
        $tables = [];

        foreach ($this->tableNames() as $table) {
            $tables[$table] = DB::table($table)->get()->map(
                fn ($row): array => (array) $row
            )->all();
        }

        return $tables;
    }

    /**
     * @return array<int, string>
     */
    protected function tableNames(): array
    {
        $driver = DB::getDriverName();
        $pgsqlTableQuery = implode(' ', [
            'select tablename',
            'from pg_tables',
            "where schemaname = 'public'",
            'order by tablename',
        ]);
        $sqliteTableQuery = implode(' ', [
            'select name',
            'from sqlite_master',
            "where type = 'table'",
            "and name not like 'sqlite_%'",
            'order by name',
        ]);
        $mysqlTableQuery = implode(' ', [
            'select table_name',
            'from information_schema.tables',
            'where table_schema = database()',
            'order by table_name',
        ]);

        return match ($driver) {
            // noinspection SqlNoDataSourceInspection
            'pgsql' => collect(DB::select($pgsqlTableQuery))
                ->pluck('tablename')
                ->all(),
            // noinspection SqlNoDataSourceInspection
            'sqlite' => collect(DB::select($sqliteTableQuery))
                ->pluck('name')
                ->all(),
            // noinspection SqlNoDataSourceInspection
            default => collect(DB::select($mysqlTableQuery))
                ->pluck('table_name')
                ->all(),
        };
    }

    /**
     * @param  array<int, string>  $excludedPrefixes
     */
    protected function addDiskDirectory(
        ZipArchive $zip,
        string $diskName,
        string $directory,
        string $zipPrefix,
        array $excludedPrefixes = [],
    ): void {
        $disk = Storage::disk($diskName);
        $files = $disk->allFiles($directory);

        foreach ($files as $file) {
            foreach ($excludedPrefixes as $excludedPrefix) {
                if ($excludedPrefix !== '' && str_starts_with($file, $excludedPrefix)) {
                    continue 2;
                }
            }

            $filePath = $disk->path($file);

            if (! is_file($filePath) || ! is_readable($filePath)) {
                continue;
            }

            $zip->addFile($filePath, $zipPrefix.'/'.ltrim($file, '/'));
        }
    }

    protected function addPublicAsset(ZipArchive $zip, string $source, string $target): void
    {
        $path = public_path($source);

        if (is_file($path) && is_readable($path)) {
            $zip->addFile($path, $target);
        }
    }

    protected function formatDuration(int $durationMs): string
    {
        return number_format($durationMs / 1000, 2).'s';
    }

    protected function ensureZipArchiveAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException(
                'The PHP zip extension is required for backups. Install/enable ext-zip so ZipArchive is available.',
            );
        }
    }

    /**
     * @throws JsonException
     */
    protected function encode(mixed $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
