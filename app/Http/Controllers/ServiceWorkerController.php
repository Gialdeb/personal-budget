<?php

namespace App\Http\Controllers;

use App\Support\Pwa\PwaManifestData;
use Illuminate\Http\Response;

class ServiceWorkerController extends Controller
{
    public function __invoke(PwaManifestData $pwaManifestData): Response
    {
        return response(
            view('pwa.service-worker', [
                'config' => $pwaManifestData->serviceWorker(),
            ])->render(),
            200,
            [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Service-Worker-Allowed' => '/',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
