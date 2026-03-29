<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\CreditCards\CreditCardAutopayService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunCreditCardAutopayJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
        public ?string $referenceDate = null,
    ) {}

    public function handle(
        AutomationPipelineRunner $runner,
        CreditCardAutopayService $creditCardAutopayService,
    ): void {
        $runner->run(
            automationKey: 'credit_card_autopay',
            pipeline: 'credit_card_autopay',
            triggerType: $this->triggerType,
            callback: function () use ($creditCardAutopayService): array {
                $referenceDate = $this->referenceDate
                    ? CarbonImmutable::parse($this->referenceDate)
                    : null;
                $result = $creditCardAutopayService->runAutomationPipeline($referenceDate);
                $errorCount = (int) ($result['error_count'] ?? 0);

                return [
                    'status' => $errorCount > 0 ? 'warning' : 'success',
                    'processed_count' => (int) ($result['processed_count'] ?? 0),
                    'success_count' => (int) ($result['success_count'] ?? 0),
                    'warning_count' => (int) ($result['warning_count'] ?? 0),
                    'error_count' => $errorCount,
                    'result' => $result,
                    'message' => $errorCount > 0
                        ? __('admin.automation.creditCardAutopay.partialFailure')
                        : null,
                ];
            },
            jobClass: self::class,
            context: array_filter([
                'reference_date' => $this->referenceDate,
            ], fn ($value) => $value !== null && $value !== ''),
            attempt: 1,
        );
    }
}
