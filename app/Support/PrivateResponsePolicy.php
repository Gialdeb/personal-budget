<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class PrivateResponsePolicy
{
    public function apply(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->setVary(['Cookie', 'Authorization', 'X-Inertia'], false);
        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');

        return $response;
    }
}
