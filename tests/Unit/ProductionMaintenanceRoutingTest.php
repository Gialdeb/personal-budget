<?php

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('maintenance proxy changes only web handlers and retains hosts TLS websocket and error routing', function () {
    $web = ['handler' => 'reverse_proxy', 'upstreams' => [['dial' => 'web:80']], 'handle_response' => [['status_code' => 503]]];
    $reverb = ['handler' => 'reverse_proxy', 'upstreams' => [['dial' => 'reverb:8080']]];
    $config = ['apps' => ['tls' => ['certificates' => ['automate' => ['budget.example']]], 'http' => ['servers' => ['public' => [
        'listen' => [':443'], 'errors' => ['routes' => [['handle' => [['handler' => 'static_response', 'body' => 'Old fallback']]]]],
        'routes' => [['match' => [['host' => ['budget.example']]], 'handle' => [['handler' => 'subroute', 'routes' => [['handle' => [$reverb]], ['handle' => [$web]]]]]]],
    ]]]]];
    $process = new Process([PHP_BINARY, base_path('docker/caddy/maintenance-config.php')]);
    $process->setInput(json_encode($config));
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    $expected = $config;
    $expected['apps']['http']['servers']['public']['routes'][0]['handle'][0]['routes'][1]['handle'][0] = ['handler' => 'reverse_proxy', 'upstreams' => [['dial' => 'maintenance:8000']]];
    expect($result)->toBe($expected);
});

test('a missing app upstream rejects the switch before any runtime routing change', function () {
    $process = new Process([PHP_BINARY, base_path('docker/caddy/maintenance-config.php')]);
    $process->setInput('{}');
    $process->run();
    expect($process->getExitCode())->toBe(1)->and($process->getOutput())->toBe('');
});

test('maintenance responder renders the exact existing Soamco view in the requested language and supports Inertia reload', function () {
    $source = file_get_contents(base_path('docker/php/maintenance-router.php'));
    foreach (['it', 'en', 'it;q=0.5,en;q=1'] as $language) {
        $request = Request::create('/dashboard', server: ['HTTP_ACCEPT_LANGUAGE' => $language]);
        app()->instance('request', $request);
        $expected = view('errors.503')->render();
        $code = '$_SERVER["REQUEST_URI"]="/dashboard"; $_SERVER["HTTP_ACCEPT_LANGUAGE"]='.var_export($language, true).'; require '.var_export(base_path('docker/php/maintenance-router.php'), true).';';
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'log', 'NIGHTWATCH_ENABLED' => 'false']);
        $process->mustRun();
        expect($process->getOutput())->toBe($expected);
    }
    expect($source)->toContain("header('Retry-After: 60')", 'http_response_code(503)', 'http_response_code(409)', 'X-Inertia-Location');
});
