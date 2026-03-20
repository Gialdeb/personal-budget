<?php

namespace App\Supports\Imports;

class ImportFingerprintGenerator
{
    public static function make(array $normalizedPayload, int $userId, ?int $accountId): string
    {
        $source = [
            'user_id' => $userId,
            'account_id' => $accountId,
            'date' => $normalizedPayload['date'] ?? null,
            'type' => $normalizedPayload['type'] ?? null,
            'amount' => $normalizedPayload['amount'] ?? null,
            'detail' => self::normalizeText($normalizedPayload['detail'] ?? null),
            'external_reference' => self::normalizeText($normalizedPayload['external_reference'] ?? null),
        ];

        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected static function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value ?: null;
    }
}
