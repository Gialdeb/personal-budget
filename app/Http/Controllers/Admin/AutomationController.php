<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AutomationTriggerTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunAutomationPipelineRequest;
use App\Http\Resources\Admin\AutomationRunResource;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationDispatcher;
use App\Services\Automation\AutomationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AutomationController extends Controller
{
    public function index(Request $request, AutomationStatusService $statusService): Response
    {
        $user = $request->user();
        $canAccess = $user
            && ((method_exists($user, 'hasRole') && $user->hasRole('admin'))
                || (bool) ($user->is_admin ?? false));

        abort_unless($canAccess, 403);

        $query = AutomationRun::query()->latest('created_at');

        if ($pipeline = $request->string('pipeline')->toString()) {
            $query->where('automation_key', $pipeline);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($triggerType = $request->string('trigger_type')->toString()) {
            $query->where('trigger_type', $triggerType);
        }

        $runs = $query->paginate(25)->withQueryString();

        return Inertia::render('admin/Automation/Index', [
            'runs' => AutomationRunResource::collection($runs),
            'statuses' => $statusService->pipelineStatuses(),
            'filters' => [
                'pipeline' => $request->string('pipeline')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'trigger_type' => $request->string('trigger_type')->toString() ?: null,
            ],
            'options' => [
                'pipelines' => array_keys(config('automation.pipelines', [])),
                'statuses' => ['pending', 'running', 'success', 'warning', 'failed', 'skipped', 'timed_out'],
                'trigger_types' => ['scheduled', 'manual', 'retry', 'system'],
            ],
        ]);
    }

    public function show(Request $request, AutomationRun $automationRun): Response
    {
        $user = $request->user();
        $canAccess = $user
            && ((method_exists($user, 'hasRole') && $user->hasRole('admin'))
                || (bool) ($user->is_admin ?? false));

        abort_unless($canAccess, 403);

        return Inertia::render('admin/Automation/Show', [
            'run' => (new AutomationRunResource($automationRun))->resolve(),
        ]);
    }

    public function run(
        RunAutomationPipelineRequest $request,
        string $pipeline,
        AutomationDispatcher $dispatcher,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $dispatcher->dispatchPipeline(
                $pipeline,
                AutomationTriggerTypeEnum::MANUAL,
                array_filter([
                    'reference_date' => $validated['reference_date'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('admin.automation.flash.dispatched'));
    }

    public function retry(
        RunAutomationPipelineRequest $request,
        AutomationRun $automationRun,
        AutomationDispatcher $dispatcher,
    ): RedirectResponse {
        try {
            $dispatcher->retryRun($automationRun);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('admin.automation.flash.retried'));
    }
}
