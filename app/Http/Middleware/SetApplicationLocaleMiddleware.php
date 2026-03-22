<?php

namespace App\Http\Middleware;

use App\Supports\Locale\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocaleMiddleware
{
    public function __construct(
        protected LocaleResolver $localeResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(
            $this->localeResolver->current($request->user())
        );

        return $next($request);
    }
}
