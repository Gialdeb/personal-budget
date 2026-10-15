<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$request = Request::capture();
$app->instance('request', $request);

if ($request->path() === '__maintenance_health') {
    http_response_code(200);
    echo 'ready';

    return;
}

header('Cache-Control: no-store, private');
if ($request->header('X-Inertia')) {
    http_response_code(409);
    header('X-Inertia-Location: '.$request->getRequestUri());

    return;
}

http_response_code(503);
header('Content-Type: text/html; charset=UTF-8');
header('Retry-After: 60');
header('Refresh: 10');
echo view('errors.503')->render();
