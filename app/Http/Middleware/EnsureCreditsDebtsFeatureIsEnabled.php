<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCreditsDebtsFeatureIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        return $next($request);
    }
}
