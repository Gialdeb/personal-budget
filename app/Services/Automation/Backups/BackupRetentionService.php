<?php

namespace App\Services\Automation\Backups;

use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    public function run(): array
    {
        $disk = Storage::disk(config('automation.backups.disk', 'local'));
        $targets = [
            'full' => [
                'directory' => trim((string) config('automation.backups.full.directory', 'backups/full'), '/'),
                'prefix' => FullBackupService::ARCHIVE_PREFIX,
                'retention_days' => (int) config('automation.backups.retention.full.days', 90),
            ],
            'user' => [
                'directory' => trim((string) config('automation.backups.user.directory', 'backups/users'), '/'),
                'prefix' => UserBackupService::ARCHIVE_PREFIX,
                'retention_days' => (int) config('automation.backups.retention.user.days', 90),
            ],
        ];

        $deletedPaths = [];
        $keptPaths = [];
        $inspectedCount = 0;

        foreach ($targets as $target => $configuration) {
            if (! $this->isRetentionEnabledFor($target, $configuration['retention_days'])) {
                continue;
            }

            $cutoffTimestamp = now()->subDays($configuration['retention_days'])->getTimestamp();

            foreach ($disk->allFiles($configuration['directory']) as $path) {
                if (! $this->isManagedBackupPath($path, $configuration['directory'], $configuration['prefix'])) {
                    continue;
                }

                $inspectedCount++;

                if ($disk->lastModified($path) > $cutoffTimestamp) {
                    $keptPaths[] = $path;

                    continue;
                }

                if (! $disk->delete($path)) {
                    throw new \RuntimeException("Unable to delete expired backup archive [{$path}].");
                }

                $deletedPaths[] = $path;
            }
        }

        return [
            'summary' => 'Pulizia retention backup completata.',
            'inspected_count' => $inspectedCount,
            'deleted_count' => count($deletedPaths),
            'kept_count' => count($keptPaths),
            'deleted_paths' => $deletedPaths,
            'kept_paths' => $keptPaths,
        ];
    }

    protected function isRetentionEnabledFor(string $target, int $retentionDays): bool
    {
        if (! (bool) config('automation.backups.retention.enabled', true)) {
            return false;
        }

        if (! (bool) config("automation.backups.retention.{$target}.enabled", true)) {
            return false;
        }

        return $retentionDays > 0;
    }

    protected function isManagedBackupPath(string $path, string $directory, string $prefix): bool
    {
        if ($directory === '') {
            return false;
        }

        if (! str_starts_with($path, $directory.'/')) {
            return false;
        }

        $filename = basename($path);

        return str_starts_with($filename, $prefix) && str_ends_with($filename, '.zip');
    }
}
