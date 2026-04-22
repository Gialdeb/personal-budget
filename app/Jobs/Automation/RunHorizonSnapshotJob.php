<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunHorizonSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
    ) {}

    public function handle(AutomationPipelineRunner $runner): void
    {
        $runner->run(
            automationKey: 'horizon_snapshot',
            pipeline: 'horizon_snapshot',
            triggerType: $this->triggerType,
            callback: function (): array {
                $exitCode = Artisan::call('horizon:snapshot');
                $output = trim(Artisan::output());

                if ($exitCode !== self::SUCCESS_EXIT_CODE) {
                    throw new RuntimeException($output !== '' ? $output : 'horizon:snapshot failed.');
                }

                return [
                    'status' => 'success',
                    'result' => [
                        'summary' => 'Horizon snapshot stored successfully.',
                        'command' => 'horizon:snapshot',
                        'exit_code' => $exitCode,
                        'output' => $output,
                    ],
                ];
            },
            jobClass: self::class,
            attempt: 1,
        );
    }

    private const SUCCESS_EXIT_CODE = 0;
}
