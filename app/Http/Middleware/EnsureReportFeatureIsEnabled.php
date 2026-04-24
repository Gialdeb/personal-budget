<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReportFeatureIsEnabled
{
    public function handle(Request $request, Closure $next, ?string $section = null): Response
    {
        abort_unless((bool) config('features.reports.enabled'), 404);

        if ($section !== null) {
            abort_unless((bool) config("features.reports.sections.{$section}"), 404);
        }

        return $next($request);
    }
}
