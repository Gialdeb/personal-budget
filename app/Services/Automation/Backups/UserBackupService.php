<?php

namespace App\Services\Automation\Backups;

use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\AccountOpeningBalance;
use App\Models\AccountReconciliation;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\TransactionMatcher;
use App\Models\TransactionReview;
use App\Models\TransactionSplit;
use App\Models\TransactionTrainingSample;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserNotificationPreference;
use App\Models\UserSetting;
use App\Models\UserYear;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use JsonException;
use ZipArchive;

class UserBackupService
{
    public const ARCHIVE_PREFIX = 'user-backup-';

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
            throw new \RuntimeException('Unable to create user backup archive.');
        }

        $users = User::query()->orderBy('id')->get();

        try {
            $manifest = [
                'type' => 'user_backup',
                'artifact_classification' => 'user_snapshot_archive',
                'restore_capability' => [
                    'is_automated_restore_available' => false,
                    'is_end_to_end_restorable' => false,
                    'level' => 'structured_export_for_targeted_restore',
                    'notes' => [
                        'The archive contains one normalized JSON snapshot per user.',
                        'No automated restore writer is implemented yet.',
                        'The structure is stable and intended for future targeted user restore tooling.',
                    ],
                ],
                'environment' => app()->environment(),
                'created_at' => now()->toIso8601String(),
                'user_count' => $users->count(),
                'includes' => [
                    'profile',
                    'user_settings',
                    'notification_preferences',
                    'years',
                    'scopes',
                    'user_banks',
                    'tracked_items',
                    'categories',
                    'merchants',
                    'accounts',
                    'account_opening_balances',
                    'account_balance_snapshots',
                    'account_reconciliations',
                    'transactions',
                    'transaction_splits',
                    'transaction_reviews',
                    'transaction_matchers',
                    'transaction_training_samples',
                    'recurring_entries',
                    'recurring_entry_occurrences',
                    'scheduled_entries',
                    'budgets',
                    'imports',
                    'import_rows',
                ],
            ];

            $zip->addFromString('manifest.json', $this->encode($manifest));

            foreach ($users as $user) {
                $snapshot = $this->snapshotUser($user);

                $zip->addFromString(
                    'users/'.$user->uuid.'/data.json',
                    $this->encode($snapshot),
                );
            }
        } finally {
            $zip->close();
        }

        $sizeBytes = is_file($absolutePath) ? filesize($absolutePath) ?: 0 : 0;
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'summary' => 'Backup utenti generato correttamente.',
            'path' => $relativePath,
            'absolute_path' => $absolutePath,
            'size_bytes' => $sizeBytes,
            'size_human' => Number::fileSize($sizeBytes),
            'duration_ms' => $durationMs,
            'duration_human' => $this->formatDuration($durationMs),
            'user_count' => $users->count(),
            'subject' => 'tutti gli utenti applicativi',
            'restore_capability' => 'structured_export_for_targeted_restore',
        ];
    }

    public function directory(): string
    {
        return trim((string) config('automation.backups.user.directory', 'backups/users'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotUser(User $user): array
    {
        $accountIds = Account::query()->where('user_id', $user->id)->pluck('id');
        $transactionIds = Transaction::query()->where('user_id', $user->id)->pluck('id');
        $importIds = Import::query()->where('user_id', $user->id)->pluck('id');
        $recurringEntryIds = RecurringEntry::query()->where('user_id', $user->id)->pluck('id');

        return [
            'profile' => $this->normalizeOne($user),
            'user_settings' => $this->normalizeOne(UserSetting::query()->where('user_id', $user->id)->first()),
            'notification_preferences' => $this->normalizeCollection(
                UserNotificationPreference::query()->where('user_id', $user->id)->orderBy('id')->get()
            ),
            'years' => $this->normalizeCollection(UserYear::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'scopes' => $this->normalizeCollection(Scope::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'user_banks' => $this->normalizeCollection(UserBank::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'tracked_items' => $this->normalizeCollection(TrackedItem::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'categories' => $this->normalizeCollection(Category::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'merchants' => $this->normalizeCollection(Merchant::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'accounts' => $this->normalizeCollection(Account::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'account_opening_balances' => $this->normalizeCollection(
                AccountOpeningBalance::query()->whereIn('account_id', $accountIds)->orderBy('id')->get()
            ),
            'account_balance_snapshots' => $this->normalizeCollection(
                AccountBalanceSnapshot::query()->whereIn('account_id', $accountIds)->orderBy('id')->get()
            ),
            'account_reconciliations' => $this->normalizeCollection(
                AccountReconciliation::query()->whereIn('account_id', $accountIds)->orderBy('id')->get()
            ),
            'transactions' => $this->normalizeCollection(Transaction::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'transaction_splits' => $this->normalizeCollection(
                TransactionSplit::query()->whereIn('transaction_id', $transactionIds)->orderBy('id')->get()
            ),
            'transaction_reviews' => $this->normalizeCollection(
                TransactionReview::query()->whereIn('transaction_id', $transactionIds)->orderBy('id')->get()
            ),
            'transaction_matchers' => $this->normalizeCollection(
                TransactionMatcher::query()->where('user_id', $user->id)->orderBy('id')->get()
            ),
            'transaction_training_samples' => $this->normalizeCollection(
                TransactionTrainingSample::query()->where('user_id', $user->id)->orderBy('id')->get()
            ),
            'recurring_entries' => $this->normalizeCollection(
                RecurringEntry::query()->where('user_id', $user->id)->orderBy('id')->get()
            ),
            'recurring_entry_occurrences' => $this->normalizeCollection(
                RecurringEntryOccurrence::query()->whereIn('recurring_entry_id', $recurringEntryIds)->orderBy('id')->get()
            ),
            'scheduled_entries' => $this->normalizeCollection(
                ScheduledEntry::query()->where('user_id', $user->id)->orderBy('id')->get()
            ),
            'budgets' => $this->normalizeCollection(Budget::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'imports' => $this->normalizeCollection(Import::query()->where('user_id', $user->id)->orderBy('id')->get()),
            'import_rows' => $this->normalizeCollection(
                ImportRow::query()->whereIn('import_id', $importIds)->orderBy('id')->get()
            ),
        ];
    }

    protected function normalizeOne(?object $record): ?array
    {
        return $record ? $this->normalizeRecord($record) : null;
    }

    protected function normalizeCollection(iterable $records): array
    {
        $normalized = [];

        foreach ($records as $record) {
            $normalized[] = $this->normalizeRecord($record);
        }

        return $normalized;
    }

    protected function normalizeRecord(object $record): array
    {
        if (method_exists($record, 'attributesToArray')) {
            /** @var array<string, mixed> $attributes */
            $attributes = $record->attributesToArray();

            return $attributes;
        }

        return (array) $record;
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
