<?php

namespace App\Services\Maintenance;

use App\Models\AutomationRun;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TechnicalArchiveService
{
    public const TABLES = ['automation_runs'];

    private const ARCHIVE_TABLE = 'technical_archives';

    /** @return array{table: string, analyzed: int, archived: int, deleted: int, errors: int, duration_ms: int, would_archive_or_verify: int, would_delete: int} */
    public function archive(string $table, int $retentionDays, int $batchSize, int $limit, bool $dryRun = false): array
    {
        $this->assertAllowed($table);
        if ($retentionDays < 1 || $batchSize < 1 || $batchSize > 1000 || $limit < 1) {
            throw new InvalidArgumentException('Invalid archive retention, batch size or limit.');
        }
        $started = hrtime(true);
        $stats = ['table' => $table, 'analyzed' => 0, 'archived' => 0, 'deleted' => 0, 'errors' => 0, 'duration_ms' => 0, 'would_archive_or_verify' => 0, 'would_delete' => 0];
        $cutoff = now()->subDays($retentionDays)->toDateTimeString();
        $errorClass = null;
        try {
            if ($dryRun) {
                $stats['analyzed'] = DB::query()->fromSub($this->eligible($cutoff)->select('id')->limit($limit), 'candidates')->count();
                $stats['would_archive_or_verify'] = $stats['analyzed'];
                $stats['would_delete'] = $stats['analyzed'];
            } else {
                while ($stats['analyzed'] < $limit) {
                    $batch = DB::transaction(function () use ($cutoff, $batchSize, $limit, $stats, $table): array {
                        $rows = $this->eligible($cutoff)->orderBy('id')->limit(min($batchSize, $limit - $stats['analyzed']))->lockForUpdate()->get();
                        $archived = 0;
                        $deleted = 0;
                        foreach ($rows as $row) {
                            $record = (array) $row;
                            $key = ['source_table' => $table, 'source_key' => (string) $row->id];
                            $json = $this->encodeRecord($record);
                            $existing = DB::table(self::ARCHIVE_TABLE)->where($key)->lockForUpdate()->first();
                            if ($existing === null) {
                                $compressed = gzencode($json, 6);
                                if ($compressed === false) {
                                    throw new RuntimeException('Archive compression failed.');
                                }
                                DB::table(self::ARCHIVE_TABLE)->insert($key + [
                                    'original_created_at' => $row->created_at,
                                    'archived_at' => now(), 'payload_version' => 1,
                                    'payload_codec' => 'gzip-base64-json', 'checksum' => hash('sha256', $json),
                                    'payload' => base64_encode($compressed),
                                ]);
                                $archived++;
                            }
                            $stored = $this->retrieve($table, (string) $row->id);
                            if ($stored === null || $this->encodeRecord($stored) !== $json) {
                                throw new RuntimeException('Archive verification failed; original record retained.');
                            }
                            $affected = DB::table($table)->where('id', $row->id)->delete();
                            if ($affected !== 1) {
                                throw new RuntimeException('Original deletion was not completed; batch rolled back.');
                            }
                            $deleted += $affected;
                        }

                        return ['analyzed' => $rows->count(), 'archived' => $archived, 'deleted' => $deleted];
                    }, 3);
                    foreach ($batch as $key => $value) {
                        $stats[$key] += $value;
                    }
                    if ($batch['analyzed'] === 0) {
                        break;
                    }
                }
            }
        } catch (Throwable $exception) {
            $stats['errors']++;
            $errorClass = $exception::class;
            throw $exception;
        } finally {
            $stats['duration_ms'] = (int) ((hrtime(true) - $started) / 1_000_000);
            Log::info('Technical archive maintenance', $stats + ['dry_run' => $dryRun, 'exception_class' => $errorClass]);
        }

        return $stats;
    }

    /** @return array<string, mixed>|null */
    public function retrieve(string $table, string $sourceKey): ?array
    {
        $this->assertAllowed($table);
        $row = DB::table(self::ARCHIVE_TABLE)->where('source_table', $table)->where('source_key', $sourceKey)->first();
        if ($row === null) {
            return null;
        }
        if ((int) $row->payload_version !== 1 || $row->payload_codec !== 'gzip-base64-json') {
            throw new RuntimeException('Unsupported archive payload format.');
        }
        $compressed = base64_decode($row->payload, true);
        $json = $compressed === false ? false : @gzdecode($compressed);
        if ($json === false || ! hash_equals($row->checksum, hash('sha256', $json))) {
            throw new RuntimeException('Archive checksum verification failed.');
        }
        $record = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($record) || (string) ($record['id'] ?? '') !== $sourceKey) {
            throw new RuntimeException('Archive source identity mismatch.');
        }

        return $record;
    }

    private function eligible(string $cutoff): Builder
    {
        $table = (new AutomationRun)->getTable();

        return DB::table($table)->whereIn('status', ['success', 'skipped'])
            ->where('finished_at', '<', $cutoff)->where('created_at', '<', $cutoff)->where('updated_at', '<', $cutoff)
            ->whereExists(function (Builder $query) use ($table): void {
                $query->selectRaw('1')->from($table.' as newer')
                    ->whereColumn('newer.automation_key', $table.'.automation_key')
                    ->where(function (Builder $newer) use ($table): void {
                        $newer->whereColumn('newer.created_at', '>', $table.'.created_at')
                            ->orWhere(function (Builder $tie) use ($table): void {
                                $tie->whereColumn('newer.created_at', $table.'.created_at')->whereColumn('newer.id', '>', $table.'.id');
                            });
                    });
            });
    }

    /** @param array<string, mixed> $record */
    private function encodeRecord(array $record): string
    {
        ksort($record);

        return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function assertAllowed(string $table): void
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException('Table is not eligible for technical archiving.');
        }
    }
}
