<?php

use Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer;

return [
    'enabled' => env('LOG_VIEWER_ENABLED', true),
    'route_path' => 'admin/logs',
    'middleware' => ['web', 'auth', 'verified', 'not_banned', 'role:admin', AuthorizeLogViewer::class],
    'api_middleware' => ['web', 'auth', 'verified', 'not_banned', 'role:admin', AuthorizeLogViewer::class],
    'include_files' => [storage_path('logs/*.log')],
    'back_to_system_url' => rtrim(config('app.url'), '/').'/admin',
];
