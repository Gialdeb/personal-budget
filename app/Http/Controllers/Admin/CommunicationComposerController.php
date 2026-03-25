<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewCommunicationRequest;
use App\Http\Requests\Admin\SendCommunicationRequest;
use App\Http\Resources\Admin\ManualCommunicationCategoryResource;
use App\Http\Resources\Admin\ManualCommunicationDispatchResultResource;
use App\Http\Resources\Admin\ManualCommunicationPreviewResource;
use App\Http\Resources\Admin\ManualCommunicationRecipientResource;
use App\Models\User;
use App\Services\Communication\CommunicationChannelRegistry;
use App\Services\Communication\CommunicationComposerService;
use App\Services\Communication\CommunicationDispatchService;
use App\Services\Communication\ManualCommunicationCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationComposerController extends Controller
{
    public function index(
        Request $request,
        ManualCommunicationCatalogService $catalog,
        CommunicationChannelRegistry $channelRegistry,
    ): Response {
        $categories = $catalog->manualCategoriesQuery()
            ->get()
            ->filter(fn ($category) => $catalog->availableForManualSend($category))
            ->values();

        return Inertia::render('admin/Communications/Compose', [
            'categories' => ManualCommunicationCategoryResource::collection($categories)->resolve(),
            'channels' => collect($channelRegistry->values())
                ->map(fn (string $channel) => [
                    'value' => $channel,
                    'label' => __("admin.communication_composer.channels.{$channel}"),
                ])
                ->values()
                ->all(),
            'locale_options' => collect(config('locales.supported', []))
                ->map(fn (array $locale, string $code) => [
                    'value' => $code,
                    'label' => $locale['label'] ?? strtoupper($code),
                ])
                ->prepend([
                    'value' => 'recipient',
                    'label' => __('admin.communication_composer.locales.recipient'),
                ])
                ->values()
                ->all(),
            'content_modes' => [
                ['value' => 'template', 'label' => __('admin.communication_composer.content_modes.template')],
                ['value' => 'custom', 'label' => __('admin.communication_composer.content_modes.custom')],
            ],
            'recipient_lookup_url' => route('admin.communications.compose.recipients'),
            'preview_url' => route('admin.communications.compose.preview'),
            'send_url' => route('admin.communications.compose.send'),
        ]);
    }

    public function recipients(Request $request, ManualCommunicationCatalogService $catalog): JsonResponse
    {
        $recipients = $catalog->recipientQuery($request->string('search')->toString())
            ->limit(12)
            ->get();

        return response()->json([
            'data' => ManualCommunicationRecipientResource::collection($recipients)->resolve(),
        ]);
    }

    public function preview(
        PreviewCommunicationRequest $request,
        CommunicationComposerService $composerService,
    ): JsonResponse {
        /** @var Collection<int, User> $recipients */
        $recipients = $request->recipients();
        $sampleRecipient = $recipients->firstOrFail();
        $category = $request->category();
        $categoryResource = (new ManualCommunicationCategoryResource($category))->resolve();

        $previews = collect($request->channels())
            ->map(function ($channel) use ($composerService, $request, $sampleRecipient): array {
                $composed = $composerService->compose(
                    $request->category()->key,
                    $channel,
                    $sampleRecipient,
                    $request->forcedLocaleFor($sampleRecipient),
                    $request->customContent(),
                );

                return [
                    'composed' => $composed,
                    'context' => [
                        'type' => 'user',
                        'uuid' => $sampleRecipient->uuid,
                        'label' => trim(implode(' ', array_filter([$sampleRecipient->name, $sampleRecipient->surname]))) ?: $sampleRecipient->email,
                    ],
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => (new ManualCommunicationPreviewResource([
                'category' => [
                    'uuid' => $categoryResource['uuid'],
                    'key' => $categoryResource['key'],
                    'name' => $categoryResource['name'],
                ],
                'sample_recipient' => (new ManualCommunicationRecipientResource($sampleRecipient))->resolve(),
                'recipient_count' => $recipients->count(),
                'locale' => [
                    'value' => (string) $request->input('locale'),
                    'label' => (string) collect(config('locales.supported', []))
                        ->mapWithKeys(fn (array $locale, string $code) => [$code => $locale['label'] ?? strtoupper($code)])
                        ->prepend(__('admin.communication_composer.locales.recipient'), 'recipient')
                        ->get((string) $request->input('locale'), strtoupper((string) $request->input('locale'))),
                ],
                'content_mode' => $request->contentMode(),
                'previews' => $previews,
            ]))->resolve(),
        ]);
    }

    public function send(
        SendCommunicationRequest $request,
        CommunicationDispatchService $dispatchService,
    ): JsonResponse {
        $messages = $dispatchService->dispatchManualBatch(
            categoryKey: $request->category()->key,
            channels: $request->channels(),
            recipients: $request->recipients()->all(),
            actor: $request->user(),
            forcedLocale: $request->input('locale') === 'recipient' ? null : (string) $request->input('locale'),
            contentOverrides: $request->customContent(),
        );

        return response()->json([
            'message' => __('admin.communication_composer.flash.sent'),
            'data' => (new ManualCommunicationDispatchResultResource([
                'messages' => collect($messages)->map->fresh()->all(),
            ]))->resolve(),
        ]);
    }
}
