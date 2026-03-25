<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommunicationOutboundIndexRequest;
use App\Http\Resources\Admin\CommunicationOutboundDetailResource;
use App\Http\Resources\Admin\CommunicationOutboundResource;
use App\Models\OutboundMessage;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationOutboundController extends Controller
{
    public function index(CommunicationOutboundIndexRequest $request): Response
    {
        $filters = $request->validated();

        $outboundMessages = OutboundMessage::query()
            ->with(['category', 'template.notificationTopic', 'recipient', 'context'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('subject_resolved', 'like', "%{$search}%")
                        ->orWhere('title_resolved', 'like', "%{$search}%")
                        ->orWhere('body_resolved', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%")
                        ->orWhereHas('category', function (Builder $categoryQuery) use ($search): void {
                            $categoryQuery
                                ->where('key', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHasMorph('recipient', '*', function (Builder $recipientQuery) use ($search): void {
                            $recipientQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('surname', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['category'] ?? null, function (Builder $query, string $category): void {
                $query->whereHas('category', function (Builder $categoryQuery) use ($category): void {
                    $categoryQuery->where('key', $category);
                });
            })
            ->when($filters['recipient'] ?? null, function (Builder $query, string $recipient): void {
                $query->whereHasMorph('recipient', '*', function (Builder $recipientQuery) use ($recipient): void {
                    $recipientQuery
                        ->where('name', 'like', "%{$recipient}%")
                        ->orWhere('surname', 'like', "%{$recipient}%")
                        ->orWhere('email', 'like', "%{$recipient}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $categoryOptions = OutboundMessage::query()
            ->with('category')
            ->get()
            ->map(fn (OutboundMessage $message) => $message->category)
            ->filter()
            ->unique('uuid')
            ->sortBy('name')
            ->values()
            ->map(fn ($category) => [
                'value' => $category->key,
                'label' => $category->name,
            ])
            ->all();

        return Inertia::render('admin/Communications/Outbound/Index', [
            'outboundMessages' => CommunicationOutboundResource::collection($outboundMessages),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? null,
                'channel' => $filters['channel'] ?? null,
                'category' => $filters['category'] ?? null,
                'recipient' => $filters['recipient'] ?? '',
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'options' => [
                'statuses' => ['queued', 'sent', 'failed', 'skipped'],
                'channels' => ['mail', 'database', 'sms'],
                'categories' => $categoryOptions,
            ],
        ]);
    }

    public function show(OutboundMessage $outboundMessage): Response
    {
        $outboundMessage->load(['category', 'template.notificationTopic', 'recipient', 'context', 'creator']);

        return Inertia::render('admin/Communications/Outbound/Show', [
            'outboundMessage' => (new CommunicationOutboundDetailResource($outboundMessage))->resolve(),
        ]);
    }
}
