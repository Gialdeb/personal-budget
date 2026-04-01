<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\KofiWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KofiWebhookController extends Controller
{
    public function __invoke(Request $request, KofiWebhookService $kofiWebhookService): JsonResponse
    {
        $result = $kofiWebhookService->handle($request);

        return response()->json([
            'status' => $result['status'],
        ], $result['http_status']);
    }
}
