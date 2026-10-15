<?php

namespace App\Services\CreditDebts;

use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartySourceLink;
use App\Models\CreditDebtItem;
use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ManualEconomicSubjectResolver
{
    public function normalize(CreditDebtItem $item, bool $counterpartyProvided = false): void
    {
        $reference = $item->reference_id;
        if (! $item->exists && $reference === null && $item->counterparty_id !== null) {
            $reference = $item->counterparty_id;
        }
        $party = $reference === null ? null : TrackedItem::query()->find($reference);
        $unchanged = $item->exists && (int) $item->getRawOriginal('reference_id') === (int) $reference;
        if ($party === null || $party->user_id !== (int) $item->user_id || $party->account_id !== null || (! $party->is_active && ! $unchanged)) {
            throw ValidationException::withMessages(['reference_id' => 'Seleziona un soggetto economico personale valido e appartenente all’utente.']);
        }
        if (($counterpartyProvided || $item->isDirty('counterparty_id')) && $item->counterparty_id !== null && (int) $item->counterparty_id !== (int) $reference) {
            throw ValidationException::withMessages(['counterparty_id' => 'La controparte tecnica deve coincidere con il riferimento.']);
        }
        if ($item->exists && ! $item->isDirty('reference_id') && ($item->getRawOriginal('reference_id') === null || $item->getRawOriginal('counterparty_id') === null || (int) $item->getRawOriginal('reference_id') !== (int) $item->getRawOriginal('counterparty_id'))) {
            throw ValidationException::withMessages(['reference_id' => 'Riallinea esplicitamente il soggetto legacy prima di modificare la posizione.']);
        }
        $changed = $item->exists && ((int) $item->getRawOriginal('reference_id') !== (int) $reference || (int) $item->getRawOriginal('counterparty_id') !== (int) $reference || $item->isDirty(['user_id', 'currency_code']));
        if ($changed && ($item->payments()->withTrashed()->exists() || $this->captured($item))) {
            throw ValidationException::withMessages(['reference_id' => 'Il soggetto ha uno storico regolato o ledger: serve una rettifica dedicata.']);
        }
        $item->reference_id = $reference;
        $item->counterparty_id = $reference;
    }

    public function consistent(CreditDebtItem $item): bool
    {
        if ($item->reference_id === null || $item->counterparty_id === null || (int) $item->reference_id !== (int) $item->counterparty_id) {
            return false;
        }
        $party = $item->relationLoaded('reference') ? $item->reference : TrackedItem::query()->find($item->reference_id);

        return $party !== null && $party->user_id === (int) $item->user_id && $party->account_id === null;
    }

    public function assertConsistent(CreditDebtItem $item): void
    {
        if (! $this->consistent($item)) {
            throw ValidationException::withMessages(['reference_id' => 'Identità economica manuale incoerente: riallinea il riferimento prima di pagare o importare nel ledger.']);
        }
    }

    /** @return list<array{code: string, source: string, message: string}> */
    public function relationIssues(User $user, TrackedItem $party, string $currency): array
    {
        return CreditDebtItem::query()->forUser($user)->where('currency_code', $currency)
            ->where(fn ($query) => $query->where('counterparty_id', $party->id)->orWhere('reference_id', $party->id))
            ->with('reference')->get()->reject(fn (CreditDebtItem $item): bool => $this->consistent($item))
            ->map(fn (CreditDebtItem $item): array => ['code' => 'manual_subject_mismatch', 'source' => $item->uuid, 'message' => 'Manual reference and technical counterparty require alignment.'])
            ->values()->all();
    }

    public function confirmationBlockReason(CreditDebtItem $item): ?string
    {
        if ($item->trashed()) {
            return __('credit_debts.validation.reference_confirmation_deleted');
        }
        $attributes = $item->getAttributes();
        $captured = array_key_exists('reference_capture_0', $attributes)
            ? $attributes['reference_capture_0'] !== null || $attributes['reference_capture_1'] !== null || $attributes['reference_capture_2'] !== null
            : $this->captured($item);
        if ($captured) {
            return __('credit_debts.validation.reference_confirmation_captured');
        }
        $hasPayments = array_key_exists('reference_has_payment_history', $attributes)
            ? (bool) $attributes['reference_has_payment_history']
            : $item->payments()->withTrashed()->exists();
        if ($hasPayments) {
            return __('credit_debts.validation.reference_confirmation_payments');
        }

        return null;
    }

    public function captured(CreditDebtItem $item): bool
    {
        return CounterpartyObligation::query()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->exists()
            || CounterpartyLedgerEntry::query()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->exists()
            || CounterpartySourceLink::query()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->exists();
    }

    /** @return array{classification: string, status: string, reason: string, target: ?int, soft_deleted: bool, has_payment_history: bool, ledger_captured: bool} */
    public function classify(CreditDebtItem $item): array
    {
        $reference = $item->reference_id;
        $counterparty = $item->counterparty_id;
        $classification = $reference === null ? ($counterparty === null ? 'neither' : 'counterparty_only') : ($counterparty === null ? 'reference_only' : ($reference === $counterparty ? 'equal' : 'different'));
        $status = 'blocked';
        $reason = 'economic_subject_not_documented';
        $softDeleted = $item->trashed();
        $hasPaymentHistory = $item->payments()->withTrashed()->exists();
        $ledgerCaptured = $this->captured($item);
        $target = $reference ?? $counterparty;
        $party = $target === null ? null : TrackedItem::query()->find($target);
        if ($softDeleted) {
            $reason = 'soft_deleted';
        } elseif ($this->consistent($item)) {
            $status = 'unchanged';
            $reason = 'already_aligned';
        } elseif ($ledgerCaptured) {
            $reason = 'ledger_captured_requires_rectification';
        } elseif ($hasPaymentHistory) {
            $reason = 'payment_history_requires_review';
        } elseif ($party !== null && $party->user_id === (int) $item->user_id && $party->account_id === null && $party->is_active
            && ($party->isEconomicSubject() || $party->is_shared_expense_counterpart || ($reference === null && $counterparty !== null))) {
            $status = 'safe';
            $reason = $reference === null ? 'documented_legacy_counterparty' : 'documented_economic_reference';
        }

        return [...compact('classification', 'status', 'reason', 'target'), 'soft_deleted' => $softDeleted, 'has_payment_history' => $hasPaymentHistory, 'ledger_captured' => $ledgerCaptured];
    }
}
