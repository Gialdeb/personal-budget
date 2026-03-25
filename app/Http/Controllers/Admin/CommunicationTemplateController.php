<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use App\Enums\CommunicationTemplateOverrideScopeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertCommunicationTemplateOverrideRequest;
use App\Http\Resources\Admin\CommunicationTemplateDetailResource;
use App\Http\Resources\Admin\CommunicationTemplateResource;
use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationTemplateOverrideService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CommunicationTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $channelValues = array_map(
            static fn (CommunicationChannelEnum $channel): string => $channel->value,
            CommunicationChannelEnum::cases(),
        );
        $templateModeValues = array_map(
            static fn (CommunicationTemplateModeEnum $mode): string => $mode->value,
            CommunicationTemplateModeEnum::cases(),
        );
        $search = trim((string) $request->query('search', ''));
        $channel = $this->filterValue($request->query('channel'));
        $templateMode = $this->filterValue($request->query('template_mode'));
        $overrideState = $this->filterValue($request->query('override_state'));
        $lockState = $this->filterValue($request->query('lock_state'));

        $templates = CommunicationTemplate::query()
            ->with([
                'notificationTopic',
                'overrides' => fn ($query) => $query
                    ->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value)
                    ->latest('id'),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhereHas('notificationTopic', function (Builder $topicQuery) use ($search): void {
                            $topicQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('key', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                in_array($channel, $channelValues, true),
                fn (Builder $query) => $query->where('channel', $channel)
            )
            ->when(
                in_array($templateMode, $templateModeValues, true),
                fn (Builder $query) => $query->where('template_mode', $templateMode)
            )
            ->when($overrideState === 'with_override', function (Builder $query): void {
                $query->whereHas('overrides', fn (Builder $subQuery) => $subQuery->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value));
            })
            ->when($overrideState === 'without_override', function (Builder $query): void {
                $query->whereDoesntHave('overrides', fn (Builder $subQuery) => $subQuery->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value));
            })
            ->when($lockState === 'locked', fn (Builder $query) => $query->where('is_system_locked', true))
            ->when($lockState === 'editable', fn (Builder $query) => $query->where('is_system_locked', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/CommunicationTemplates/Index', [
            'templates' => CommunicationTemplateResource::collection($templates),
            'filters' => [
                'search' => $search,
                'channel' => $channel,
                'template_mode' => $templateMode,
                'override_state' => $overrideState,
                'lock_state' => $lockState,
            ],
            'options' => [
                'channels' => $channelValues,
                'template_modes' => $templateModeValues,
                'override_states' => ['with_override', 'without_override'],
                'lock_states' => ['locked', 'editable'],
            ],
        ]);
    }

    public function show(Request $request, CommunicationTemplate $communicationTemplate): Response
    {
        $communicationTemplate->load([
            'notificationTopic',
            'overrides' => fn ($query) => $query
                ->where('scope', 'global')
                ->latest('id'),
        ]);

        return Inertia::render('admin/CommunicationTemplates/Show', [
            'template' => (new CommunicationTemplateDetailResource($communicationTemplate))->resolve(),
        ]);
    }

    public function edit(Request $request, CommunicationTemplate $communicationTemplate): Response
    {
        $communicationTemplate->load([
            'notificationTopic',
            'overrides' => fn ($query) => $query
                ->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value)
                ->latest('id'),
        ]);

        return Inertia::render('admin/CommunicationTemplates/Edit', [
            'template' => (new CommunicationTemplateDetailResource($communicationTemplate))->resolve(),
        ]);
    }

    public function updateGlobalOverride(
        UpsertCommunicationTemplateOverrideRequest $request,
        CommunicationTemplate $communicationTemplate,
        CommunicationTemplateOverrideService $overrideService,
    ): RedirectResponse {
        try {
            $overrideService->upsertGlobalOverride($communicationTemplate, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('admin.communication_templates.flash.override_saved'));
    }

    public function disableGlobalOverride(
        Request $request,
        CommunicationTemplate $communicationTemplate,
        CommunicationTemplateOverrideService $overrideService,
    ): RedirectResponse {
        try {
            $overrideService->disableGlobalOverride($communicationTemplate);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('admin.communication_templates.flash.override_disabled'));
    }

    protected function filterValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
