<?php

namespace App\Services\Imports;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportCategorySuggestionService
{
    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @return array<string, mixed>|null
     */
    public function suggest(int $userId, array $normalizedPayload): ?array
    {
        $description = $this->normalizeSignature($normalizedPayload['detail'] ?? null);
        $merchant = $this->normalizeSignature($normalizedPayload['merchant'] ?? null);
        $accountId = is_numeric($normalizedPayload['account_id'] ?? null)
            ? (int) $normalizedPayload['account_id']
            : null;

        if ($description === null && $merchant === null) {
            return null;
        }

        $transactions = $this->candidateTransactions($userId);

        foreach ([
            ['field' => 'bank_description_clean', 'signature' => $description, 'strategy' => 'normalized_bank_description_exact', 'confidence' => 98],
            ['field' => 'description', 'signature' => $description, 'strategy' => 'normalized_description_exact', 'confidence' => 95],
            ['field' => 'counterparty_name', 'signature' => $merchant, 'strategy' => 'merchant_exact', 'confidence' => 92],
        ] as $rule) {
            if ($rule['signature'] === null) {
                continue;
            }

            $matched = $transactions->filter(fn (Transaction $transaction): bool => $this->normalizeSignature($transaction->{$rule['field']}) === $rule['signature']);
            $suggestion = $this->suggestMostFrequentCategory($matched, (string) $rule['strategy'], (int) $rule['confidence'], $accountId);

            if ($suggestion !== null) {
                return $suggestion;
            }
        }

        if ($description === null) {
            return null;
        }

        $similar = $transactions
            ->map(function (Transaction $transaction) use ($description): array {
                $candidate = $this->normalizeSignature($transaction->bank_description_clean)
                    ?? $this->normalizeSignature($transaction->description)
                    ?? $this->normalizeSignature($transaction->counterparty_name);

                if ($candidate === null) {
                    return ['transaction' => $transaction, 'score' => 0.0];
                }

                similar_text($description, $candidate, $score);

                if (str_contains($candidate, $description) || str_contains($description, $candidate)) {
                    $score = max($score, 88.0);
                }

                return ['transaction' => $transaction, 'score' => $score];
            })
            ->filter(fn (array $match): bool => $match['score'] >= 82)
            ->sortByDesc('score')
            ->take(20);

        if ($similar->isEmpty()) {
            return null;
        }

        return $this->suggestMostFrequentCategory(
            $similar->pluck('transaction'),
            'historical_similarity',
            (int) round($similar->max('score')),
            $accountId
        );
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function candidateTransactions(int $userId): Collection
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('category_id')
            ->with('category:id,uuid,name,parent_id')
            ->latest('transaction_date')
            ->limit(500)
            ->get([
                'id',
                'account_id',
                'category_id',
                'description',
                'bank_description_clean',
                'counterparty_name',
                'transaction_date',
            ]);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, mixed>|null
     */
    protected function suggestMostFrequentCategory(Collection $transactions, string $strategy, int $confidence, ?int $accountId): ?array
    {
        $categoryId = $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->category instanceof Category)
            ->groupBy('category_id')
            ->sort(function (Collection $left, Collection $right) use ($accountId): int {
                $leftSameAccountCount = $accountId !== null ? $left->where('account_id', $accountId)->count() : 0;
                $rightSameAccountCount = $accountId !== null ? $right->where('account_id', $accountId)->count() : 0;

                return [$rightSameAccountCount, $right->count()] <=> [$leftSameAccountCount, $left->count()];
            })
            ->keys()
            ->first();

        if ($categoryId === null) {
            return null;
        }

        /** @var Transaction|null $matchedTransaction */
        $matchedTransaction = $transactions->firstWhere('category_id', $categoryId);
        $category = $matchedTransaction?->category;

        if (! $category instanceof Category) {
            return null;
        }

        return [
            'category_id' => $category->id,
            'category_uuid' => $category->uuid,
            'category_label' => $category->name,
            'source' => 'historical_transactions',
            'source_label' => __('imports.suggestions.sources.historical_transactions'),
            'strategy' => $strategy,
            'confidence' => min(99, max(1, $confidence)),
            'matched_transaction_id' => $matchedTransaction?->id,
            'same_account_matches' => $accountId !== null
                ? $transactions->where('category_id', $categoryId)->where('account_id', $accountId)->count()
                : 0,
        ];
    }

    protected function normalizeSignature(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $signature = Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[[:punct:]]+/u', ' ')
            ->replaceMatches('/\b\d{2,}\b/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return $signature !== '' ? $signature : null;
    }
}
