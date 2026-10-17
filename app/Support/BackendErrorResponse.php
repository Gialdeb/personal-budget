<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
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
        if ($response->getStatusCode() >= 500) {
            $requestId ??= (string) Str::uuid();
            $copy = [
                'title' => __('errors.server.title'),
                'message' => __('errors.server.message'),
                'retryLabel' => __('errors.server.retry'),
                'backLabel' => __('errors.server.back'),
                'dashboardLabel' => __('errors.server.dashboard'),
            ];
            $props = [...$copy, 'dashboardUrl' => route('dashboard')];
            $status = $response->getStatusCode();
            if ($request->header('X-Inertia')) {
                $response = (new InertiaResponse('Error', $props))->toResponse($request)->setStatusCode($status);
            } elseif ($request->expectsJson() || $request->is('api/*')) {
                $response = response()->json([
                    'message' => $copy['message'],
                ], $status);
            } else {
                $response = response()->view('errors.server', $props, $status);
            }
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500 && $response instanceof JsonResponse) {
            $payload = $response->getData(true);
            foreach (['exception', 'file', 'line', 'trace'] as $key) {
                unset($payload[$key]);
            }
            $sanitize = function (mixed $value) use (&$sanitize): mixed {
                if (is_array($value)) {
                    return array_map($sanitize, $value);
                }
                if (is_string($value) && preg_match('/SQLSTATE|stack trace|App\\\\|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $value)) {
                    return 'L’elemento richiesto non è disponibile o non è valido. Controlla la selezione e riprova.';
                }

                return $value;
            };
            $response->setData($sanitize($payload));
        }
        if ($requestId) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }
}
