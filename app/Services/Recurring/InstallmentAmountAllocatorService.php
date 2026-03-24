<?php

namespace App\Services\Recurring;

use Illuminate\Validation\ValidationException;

class InstallmentAmountAllocatorService
{
    /**
     * @return array<int, string>
     */
    public function allocate(float $totalAmount, int $installmentsCount): array
    {
        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'total_amount' => 'Il totale del piano rateale deve essere maggiore di zero.',
            ]);
        }

        if ($installmentsCount <= 0) {
            throw ValidationException::withMessages([
                'installments_count' => 'Il numero di rate deve essere maggiore di zero.',
            ]);
        }

        $totalCents = (int) round($totalAmount * 100);
        $baseInstallment = intdiv($totalCents, $installmentsCount);
        $allocated = [];
        $runningTotal = 0;

        for ($index = 1; $index <= $installmentsCount; $index++) {
            $cents = $index === $installmentsCount
                ? $totalCents - $runningTotal
                : $baseInstallment;

            $runningTotal += $cents;
            $allocated[] = number_format($cents / 100, 2, '.', '');
        }

        return $allocated;
    }
}
