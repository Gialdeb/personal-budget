<?php

namespace App\Http\Controllers;

use App\Support\Pwa\PwaManifestData;
use Illuminate\Http\JsonResponse;

class AssetVersionController extends Controller
{
    public function __invoke(PwaManifestData $pwaManifestData): JsonResponse
    {
        return response()->json([
            'version' => $pwaManifestData->version(),
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
