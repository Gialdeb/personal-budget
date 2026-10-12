<?php

namespace App\Http\Middleware;

use App\Support\PrivateResponsePolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPrivateResponseCaching
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->routeIs('tracked-item-logos.show')
            && $response->isSuccessful()
            && str_starts_with($response->headers->get('Content-Type', ''), 'image/')) {
            return $response;
        }

        return app(PrivateResponsePolicy::class)->apply($response);
    }
}
