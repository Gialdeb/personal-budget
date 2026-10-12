<?php

namespace App\Http\Responses;

use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse extends \Laravel\Fortify\Http\Responses\LogoutResponse
{
    public function toResponse($request): Response
    {
        Inertia::clearHistory();

        return parent::toResponse($request);
    }
}
