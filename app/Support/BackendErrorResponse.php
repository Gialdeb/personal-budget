<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class BackendErrorResponse
{
    public function render(Response $response, Request $request): Response
    {
        if ($response->getStatusCode() === 503 && app()->isDownForMaintenance()) {
            if ($request->header('X-Inertia')) {
                return response('', 409, [
                    'X-Inertia-Location' => $request->fullUrl(),
                    'Cache-Control' => 'no-store, private',
                ]);
            }

            $response->headers->set('Cache-Control', 'no-store, private');

            if (! $request->expectsJson() && ! $response->headers->has('Refresh')) {
                $response->headers->set('Refresh', '10');
            }

            return $response;
        }

        $requestId = $request->attributes->get('request_id');
        if (! config('app.debug') && $response->getStatusCode() >= 500) {
            $requestId ??= (string) Str::uuid();
            $copy = [
                'title' => __('errors.server.title'),
                'message' => __('errors.server.message'),
                'codeLabel' => __('errors.server.code'),
                'retryLabel' => __('errors.server.retry'),
                'backLabel' => __('errors.server.back'),
                'dashboardLabel' => __('errors.server.dashboard'),
            ];
            $props = [...$copy, 'requestId' => $requestId, 'errorCode' => strtoupper(substr($requestId, 0, 8)), 'dashboardUrl' => route('dashboard')];
            $status = $response->getStatusCode();
            if ($request->header('X-Inertia')) {
                $response = (new InertiaResponse('Error', $props))->toResponse($request)->setStatusCode($status);
            } elseif ($request->expectsJson() || $request->is('api/*')) {
                $response = response()->json([
                    'message' => $copy['message'],
                    'request_id' => $requestId,
                    'error_code' => $props['errorCode'],
                ], $status);
            } else {
                $response = response()->view('errors.server', $props, $status);
            }
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if ($requestId) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }
}
