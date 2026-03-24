<?php

namespace App\Services\Communication;

use App\Models\User;
use Illuminate\Support\Collection;

class MonthlyReportNotificationService
{
    public function __construct(
        protected DomainNotificationService $domainNotificationService,
    ) {}

    public function notifyReady(User $user, int $year, int $month): Collection
    {
        return $this->domainNotificationService->sendMonthlyReportReady($user, [
            'year' => $year,
            'month' => $month,
            'period' => sprintf('%04d-%02d', $year, $month),
        ]);
    }
}
