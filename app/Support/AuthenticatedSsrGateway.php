<?php

namespace App\Support;

use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

class AuthenticatedSsrGateway extends HttpGateway
{
    /** @param array<string, mixed> $page */
    public function dispatch(array $page): ?Response
    {
        if (data_get($page, 'props.auth.user') !== null || request()->user() !== null) {
            return null;
        }

        return parent::dispatch($page);
    }
}
