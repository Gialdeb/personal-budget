<?php

namespace App\Services\Banks;

use Illuminate\Support\Str;

class BankDisplayNameFormatter
{
    private const MAX_DISPLAY_NAME_LENGTH = 120;

    /**
     * @var array<string, string>
     */
    private const EXACT_NAME_OVERRIDES = [
        'BANCA MONTE DEI PASCHI DI SIENA S.P.A.' => 'Monte dei Paschi',
        'CASSA DEPOSITI E PRESTITI S.P.A.' => 'Cassa Depositi e Prestiti',
        'INTESA SANPAOLO S.P.A.' => 'Intesa Sanpaolo',
    ];

    public function format(?string $officialName): ?string
    {
        if (! is_string($officialName)) {
            return null;
        }

        $officialName = trim(preg_replace('/\s+/u', ' ', $officialName) ?? '');

        if ($officialName === '') {
            return null;
        }

        $uppercaseName = Str::upper($officialName);

        if (array_key_exists($uppercaseName, self::EXACT_NAME_OVERRIDES)) {
            return $this->truncate(self::EXACT_NAME_OVERRIDES[$uppercaseName]);
        }

        $formatted = preg_replace(
            [
                '/\bS\.?\s*P\.?\s*A\.?\b/ui',
                '/\bS\.?\s*C\.?\s*P\.?\s*A\.?\b/ui',
                '/\bS\.?\s*R\.?\s*L\.?\b/ui',
                '/\bCOOPERATIVA\b/ui',
                '/\bSOCIETA\'?\s+PER\s+AZIONI\b/ui',
                '/\bSOCIETA\'?\s+COOPERATIVA\s+PER\s+AZIONI\b/ui',
                '/\bBANCA\s+POPOLARE\s+DI\b/ui',
                '/\bBANCA\s+DI\s+CREDITO\s+COOPERATIVO\s+DI\b/ui',
                '/\bCREDITO\s+COOPERATIVO\s+DI\b/ui',
                '/\bBANCA\s+DI\b/ui',
                '/\bTHE\b/ui',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            $officialName,
        );

        $formatted = trim(preg_replace('/\s+/u', ' ', (string) $formatted) ?? '');
        $formatted = trim($formatted, " \t\n\r\0\x0B,.-");

        if ($formatted === '') {
            return $this->truncate($officialName);
        }

        return $this->truncate(Str::title(Str::lower($formatted)));
    }

    private function truncate(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if (mb_strlen($value) <= self::MAX_DISPLAY_NAME_LENGTH) {
            return $value;
        }

        return rtrim(
            Str::limit(
                $value,
                self::MAX_DISPLAY_NAME_LENGTH,
                '',
                preserveWords: true,
            ),
            " \t\n\r\0\x0B,.-"
        );
    }
}
