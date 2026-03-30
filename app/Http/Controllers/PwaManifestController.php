<?php

namespace App\Http\Controllers;

use App\Support\Pwa\PwaManifestData;
use Illuminate\Http\Response;
use JsonException;

class PwaManifestController extends Controller
{
    public function __invoke(PwaManifestData $pwaManifestData): Response
    {
        try {
            $content = json_encode(
                $pwaManifestData->manifest(),
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            abort(500, 'Unable to encode the web app manifest.');
        }

        return response($content, 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'no-cache, public, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
