<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MoveTrackedItemMovementsRequest;
use App\Http\Requests\Settings\RestoreTrackedItemMovementsRequest;
use App\Services\TrackedItems\MoveTrackedItemMovementsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoveTrackedItemMovementsController extends Controller
{
    public function options(Request $request, MoveTrackedItemMovementsService $service): JsonResponse
    {
        return response()->json(['items' => $service->options($request->user())]);
    }

    public function preview(MoveTrackedItemMovementsRequest $request, MoveTrackedItemMovementsService $service): JsonResponse
    {
        return response()->json($service->preview($request->user(), $request->validated('source_uuid'), $request->validated('destination_uuid')));
    }

    public function store(MoveTrackedItemMovementsRequest $request, MoveTrackedItemMovementsService $service): JsonResponse
    {
        $service->confirm($request->user(), $request->validated('source_uuid'), $request->validated('destination_uuid'), $request->validated('preview_token'));

        return response()->json(['message' => 'Tutti i collegamenti indicati sono stati spostati. Nomi e descrizioni restano invariati. Puoi ripristinare questa operazione entro 30 giorni dallo storico.']);
    }

    public function history(Request $request, MoveTrackedItemMovementsService $service): JsonResponse
    {
        $status = $request->query('status');

        return response()->json($service->history($request->user(), max(1, $request->integer('page', 1)), in_array($status, ['pending', 'restored', 'blocked'], true) ? $status : null));
    }

    public function restore(RestoreTrackedItemMovementsRequest $request, string $uuid, MoveTrackedItemMovementsService $service): JsonResponse
    {
        $service->restore($request->user(), $uuid);

        return response()->json(['message' => 'Riferimenti originali ripristinati.']);
    }
}
