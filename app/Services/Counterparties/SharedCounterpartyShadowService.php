<?php

namespace App\Services\Counterparties;

use App\Enums\CounterpartyObligationSourceEnum;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySourceLink;
use App\Models\CounterpartySyncIssue;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class SharedCounterpartyShadowService
{
    private int $suspended = 0;

    public function __construct(private readonly SharedSourceProjector $sources, private readonly CounterpartyLedgerService $ledger, private readonly CounterpartyProjectionService $projections) {}

    public function changed(Transaction|SharedExpenseLedgerEntry|SharedExpenseSettlement $source, bool $deleted = false): void
    {
        if ($this->suspended > 0 || ($source instanceof Transaction && app(CounterpartyBankMutation::class)->permitted($source)) || (! config('counterparty_ledger.shadow_shared_enabled') && $this->activeRelationForSource($source) === null)) {
            return;
        }
        $this->capture($source, function () use ($source, $deleted): void {
            if ($source instanceof Transaction && $source->refunded_transaction_id !== null) {
                $original = Transaction::withTrashed()->find($source->refunded_transaction_id);
                if ($original !== null) {
                    $this->changed($original);
                }

                return;
            }
            if ($source instanceof Transaction) {
                $settlement = SharedExpenseSettlement::query()->where('transaction_id', $source->id)->first();
                if ($settlement !== null) {
                    $this->changed($settlement);

                    return;
                }
                if ($source->shared_expense_counterparty_id === null && ! CounterpartySourceLink::query()->where('source_type', $this->sources->sourceType($source))->where('source_identity', $source->uuid)->exists()) {
                    return;
                }
            }

            if ($source instanceof SharedExpenseSettlement) {
                $this->synchronizeSettlement($source, $deleted);
            } else {
                $this->synchronizeSource($source, $deleted);
            }
        });
    }

    public function withoutIntermediateEvents(Closure $operation): mixed
    {
        $this->suspended++;
        try {
            return $operation();
        } finally {
            $this->suspended--;
        }
    }

    /** Transfer an existing/new identity while suppressing intermediate conversion events. */
    public function convert(Transaction|SharedExpenseLedgerEntry $original, Closure $operation): Transaction|SharedExpenseLedgerEntry
    {
        $oldData = null;
        $identityFailure = null;
        $available = config('counterparty_ledger.shadow_shared_enabled') || Schema::hasTable('counterparty_source_links');
        if ($available) {
            try {
                $oldData = $this->sources->describe($original);
            } catch (Throwable $exception) {
                $identityFailure = $exception;
            }
        }
        $this->suspended++;
        try {
            $target = $operation();
        } finally {
            $this->suspended--;
        }
        if ($available) {
            $this->capture($original, function () use ($original, $target, $oldData, $identityFailure): void {
                $oldLink = CounterpartySourceLink::query()->where('source_type', $this->sources->sourceType($original))->where('source_identity', $original->uuid)->first();
                if ($oldLink === null && ! config('counterparty_ledger.shadow_shared_enabled')) {
                    return;
                }
                if ($identityFailure !== null) {
                    throw $identityFailure;
                }
                $old = $oldData;
                $new = $this->sources->describe($target);
                if ($old['user_id'] !== $new['user_id'] || $old['counterparty_id'] !== $new['counterparty_id'] || $old['currency_code'] !== $new['currency_code']) {
                    throw ValidationException::withMessages(['identity' => __('form_errors.conversion_changed_economic_ownership_or_currency')]);
                }
                $relation = $this->ledger->relation(User::query()->findOrFail($old['user_id']), TrackedItem::query()->findOrFail($old['counterparty_id']), $old['currency_code']);
                $this->link($relation->id, $old);
                $this->link($relation->id, [...$new, 'economic_identity' => $old['economic_identity']]);
                if (config('counterparty_ledger.shadow_shared_enabled') || $relation->activated_at !== null) {
                    $this->synchronizeSource($target);
                }
                $this->resolve($old['source_type'], $original->uuid);
            });
        }

        return $target;
    }

    /** Explicit import entry point; ignores feature flags and propagates errors to the relation transaction. */
    public function synchronizeSource(Transaction|SharedExpenseLedgerEntry $source, bool $deleted = false): ?CounterpartyObligation
    {
        return DB::transaction(fn (): ?CounterpartyObligation => $this->synchronizeSourceLocked($source, $deleted));
    }

    private function synchronizeSourceLocked(Transaction|SharedExpenseLedgerEntry $source, bool $deleted): ?CounterpartyObligation
    {
        $data = $this->sources->describe($source, $deleted);
        $relation = $this->ledger->relation(User::query()->findOrFail($data['user_id']), TrackedItem::query()->findOrFail($data['counterparty_id']), $data['currency_code']);
        $this->link($relation->id, $data);
        $relation = $relation->newQuery()->whereKey($relation->id)->lockForUpdate()->firstOrFail();
        $obligation = $relation->obligations()->where('economic_identity', $data['economic_identity'])->first();
        $this->assertSingleActiveSource($data, $source);
        if ($obligation === null && $data['signed_minor'] === 0) {
            $this->resolve($data['source_type'], $source->uuid);

            return null;
        }
        $snapshot = [...$data['metadata'], 'source_type' => $data['source_type'], 'source_identity' => $data['source_identity'], 'recognized_minor' => $data['signed_minor'], 'economic_date' => $data['economic_date']];
        if ($obligation === null) {
            $obligation = $this->ledger->recognize($relation, CounterpartyObligationSourceEnum::from($data['source_type']), $data['source_identity'], $data['economic_identity'], $data['signed_minor'], $data['economic_date'], 'shared:recognize:'.$data['economic_identity'], metadata: $data['metadata']);
        } else {
            $last = $obligation->ledgerEntries()->orderByDesc('id')->first();
            $previous = $last?->metadata['snapshot'] ?? [];
            if ($this->projections->recognized($obligation) !== $data['signed_minor'] || collect($snapshot)->contains(fn (mixed $value, string $field): bool => ($previous[$field] ?? null) !== $value)) {
                $version = (int) $obligation->ledgerEntries()->max('id') + 1;
                $this->ledger->replaceSharedRecognition($obligation, CounterpartyObligationSourceEnum::from($data['source_type']), $data['source_identity'], $data['signed_minor'], $data['economic_date'], 'shared:revision:'.$obligation->uuid.':'.$version, $data['metadata']);
            }
        }
        $this->resolve($data['source_type'], $source->uuid);

        return $obligation;
    }

    public function synchronizeSettlement(SharedExpenseSettlement $source, bool $deleted = false): CounterpartyPayment
    {
        return DB::transaction(fn (): CounterpartyPayment => $this->synchronizeSettlementLocked($source, $deleted));
    }

    private function synchronizeSettlementLocked(SharedExpenseSettlement $source, bool $deleted): CounterpartyPayment
    {
        $data = $this->sources->settlement($source, $deleted);
        $relation = $this->ledger->relation(User::query()->findOrFail($data['user_id']), TrackedItem::query()->findOrFail($data['counterparty_id']), 'EUR');
        $relation = $relation->newQuery()->whereKey($relation->id)->lockForUpdate()->firstOrFail();
        $payment = $relation->payments()->where('legacy_source_type', 'shared_expense_settlement')->where('legacy_source_identity', $source->uuid)->first();
        $fingerprint = hash('sha256', json_encode([...$data, 'notes' => $source->notes, 'deleted' => $deleted], JSON_THROW_ON_ERROR));
        $last = $payment?->ledgerEntries()->orderByDesc('id')->first();
        if (($last?->metadata['legacy_fingerprint'] ?? null) === $fingerprint) {
            $this->resolve('shared_expense_settlement', $source->uuid);

            return $payment;
        }
        $metadata = ['allocation_status' => 'unknown_legacy', 'legacy_origin' => 'shared_expense_settlement', 'migration_date' => $payment?->metadata['migration_date'] ?? now()->toISOString(), 'legacy_fingerprint' => $fingerprint, 'cancelled' => $deleted];
        $version = (int) ($payment?->ledgerEntries()->max('id') ?? 0) + 1;
        $payment = $this->ledger->synchronizeLegacyPayment($relation, $source, $data['effective_minor'], 'shared:settlement:'.$source->uuid.':'.$version, $metadata);
        $this->resolve('shared_expense_settlement', $source->uuid);

        return $payment;
    }

    /** @param array<string, mixed> $data */
    private function link(int $relationId, array $data): CounterpartySourceLink
    {
        $existing = CounterpartySourceLink::query()->where('source_type', $data['source_type'])->where('source_identity', $data['document_identity'] ?? $data['source_identity'])->first();
        if ($existing !== null && ($existing->relation_id !== $relationId || $existing->economic_identity !== $data['economic_identity'])) {
            throw ValidationException::withMessages(['identity' => __('form_errors.source_alias_conflicts_with_its_persistent_economic_identity')]);
        }

        return $existing ?? CounterpartySourceLink::query()->create(['relation_id' => $relationId, 'source_type' => $data['source_type'], 'source_identity' => $data['document_identity'] ?? $data['source_identity'], 'economic_identity' => $data['economic_identity']]);
    }

    /** @param array<string, mixed> $data */
    private function assertSingleActiveSource(array $data, Transaction|SharedExpenseLedgerEntry $source): void
    {
        $links = CounterpartySourceLink::query()->where('economic_identity', $data['economic_identity'])->get();
        foreach ($links as $link) {
            if (($link->source_identity === $source->uuid && $link->source_type === $data['source_type']) || ($link->source_type === 'shared_transaction_share' && array_key_exists($link->source_identity, $data['metadata']['component_snapshots'] ?? []))) {
                continue;
            }
            $other = $link->source_type === 'shared_transaction_share' ? Transaction::query()->where('uuid', $link->source_identity)->first() : SharedExpenseLedgerEntry::query()->where('uuid', $link->source_identity)->first();
            if ($other !== null && $this->sources->describe($other)['signed_minor'] !== 0) {
                throw ValidationException::withMessages(['identity' => __('form_errors.multiple_active_documents_represent_the_same_economic_identity')]);
            }
        }
    }

    private function activeRelationForSource(Transaction|SharedExpenseLedgerEntry|SharedExpenseSettlement $source): ?CounterpartyRelation
    {
        if ($source instanceof SharedExpenseSettlement) {
            return app(CounterpartyAuthority::class)->activeRelation($source->user_id, $source->counterparty_id, 'EUR');
        }
        if ($source instanceof Transaction && $source->refunded_transaction_id !== null) {
            $original = Transaction::withTrashed()->find($source->refunded_transaction_id);

            return $original === null ? null : app(CounterpartySourceGuard::class)->relation($original);
        }

        return app(CounterpartySourceGuard::class)->relation($source);
    }

    private function capture(Transaction|SharedExpenseLedgerEntry|SharedExpenseSettlement $source, Closure $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (Throwable $exception) {
            $type = $source instanceof SharedExpenseSettlement ? 'shared_expense_settlement' : $this->sources->sourceType($source);
            $partyId = $source instanceof Transaction ? $source->shared_expense_counterparty_id : $source->counterparty_id;
            $party = TrackedItem::query()->find($partyId);
            if ($this->activeRelationForSource($source) !== null) {
                throw $exception;
            }
            Log::warning('Shared counterparty shadow capture failed.', ['source_type' => $type, 'source_identity' => $source->uuid, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
            try {
                CounterpartySyncIssue::query()->updateOrCreate(['source_type' => $type, 'source_identity' => $source->uuid], ['user_id' => $party?->user_id ?? $source->user_id, 'counterparty_id' => $partyId, 'currency_code' => $source instanceof SharedExpenseSettlement ? 'EUR' : $source->currency, 'message' => $exception->getMessage(), 'resolved_at' => null]);
            } catch (Throwable $diagnosticFailure) {
                Log::error('Shared shadow diagnostic could not be persisted.', ['source_identity' => $source->uuid, 'message' => $diagnosticFailure->getMessage()]);
            }
        }
    }

    private function resolve(string $type, string $identity): void
    {
        CounterpartySyncIssue::query()->where('source_type', $type)->where('source_identity', $identity)->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }
}
