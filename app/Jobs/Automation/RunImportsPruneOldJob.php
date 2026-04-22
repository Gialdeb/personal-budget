<?php

namespace App\Jobs\Automation;

use App\Console\Commands\PruneOldImportsCommand;
use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunImportsPruneOldJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
    ) {}

    public function handle(AutomationPipelineRunner $runner): void
    {
        $runner->run(
            automationKey: 'imports_prune_old',
            pipeline: 'imports_prune_old',
            triggerType: $this->triggerType,
            callback: function (): array {
                $exitCode = Artisan::call(PruneOldImportsCommand::class);
                $output = trim(Artisan::output());

                if ($exitCode !== self::SUCCESS_EXIT_CODE) {
                    throw new RuntimeException($output !== '' ? $output : 'imports:prune-old failed.');
                }

                return [
                    'status' => 'success',
                    'result' => [
                        'summary' => 'Import prune completed successfully.',
                        'command' => 'imports:prune-old',
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
