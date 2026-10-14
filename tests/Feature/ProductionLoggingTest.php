<?php

use App\Models\User;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->logDirectory = sys_get_temp_dir().'/soamco-logging-test-'.Str::uuid();
    File::ensureDirectoryExists($this->logDirectory);
    config([
        'app.debug' => false,
        'logging.default' => 'stack',
        'logging.channels.stack.channels' => ['daily', 'stderr'],
        'logging.channels.daily.path' => $this->logDirectory.'/laravel.log',
        'logging.channels.stderr.handler_with.stream' => $this->logDirectory.'/stderr.log',
    ]);
    foreach (['stack', 'daily', 'stderr'] as $channel) {
        Log::forgetChannel($channel);
    }
});

afterEach(function () {
    foreach (['stack', 'daily', 'stderr'] as $channel) {
        Log::forgetChannel($channel);
    }
    File::deleteDirectory($this->logDirectory);
});

test('real http exceptions reach both sinks with safe correlation context', function (bool $authenticated) {
    $user = $authenticated ? User::factory()->create() : null;
    if ($user) {
        $this->actingAs($user);
    }
    Route::post('/__test/diagnostic-error', function (Request $request) {
        throw new RuntimeException('Controlled backend error '.$request->input('password').' '.$request->input('token').' '.$request->bearerToken());
    })->name('test.diagnostic-error');

    $response = $this->withHeader('Authorization', 'Bearer private-auth-sentinel')
        ->withHeader('Cookie', 'session=private-cookie-sentinel')
        ->withHeader('X-Request-ID', 'untrusted-client-id')
        ->postJson('/__test/diagnostic-error?secret=private-query-sentinel', [
            'password' => 'private-password-sentinel',
            'token' => 'private-token-sentinel',
        ]);
    $response->assertStatus(500);
    $requestId = $response->headers->get('X-Request-ID');
    expect(Str::isUuid($requestId))->toBeTrue()
        ->and($response->getContent())->not->toContain('Controlled backend error');
    foreach ([$this->logDirectory.'/laravel-'.now()->format('Y-m-d').'.log', $this->logDirectory.'/stderr.log'] as $path) {
        $contents = File::get($path);
        expect($contents)->toContain('RuntimeException', 'Controlled backend error', 'trace', 'ProductionLoggingTest.php', 'http_method', 'POST', 'test.diagnostic-error', $requestId, 'user_id')
            ->not->toContain('private-password-sentinel', 'private-token-sentinel', 'private-auth-sentinel', 'private-cookie-sentinel', 'private-query-sentinel', 'untrusted-client-id');
        if ($user) {
            expect($contents)->toContain('"user_id":'.$user->id);
        }
    }
})->with([true, false]);

test('stderr remains available when persistent log writing fails', function () {
    File::put($this->logDirectory.'/blocked', 'not a directory');
    config(['logging.channels.daily.path' => $this->logDirectory.'/blocked/laravel.log']);
    Log::error('Fallback sink check', ['exception' => new RuntimeException('disk unavailable')]);
    expect(File::get($this->logDirectory.'/stderr.log'))->toContain('Fallback sink check', 'RuntimeException');
});

test('diagnostic logs redact nested secrets and retain argument free exception traces', function () {
    Log::error('Structured redaction check', [
        'password' => 'hidden-password',
        'nested' => ['api_key' => 'hidden-api-key', 'cookie' => 'hidden-cookie'],
        'exception' => new RuntimeException('Safe diagnostic message'),
    ]);
    $contents = File::get($this->logDirectory.'/stderr.log');
    expect($contents)->toContain('[REDACTED]', 'Safe diagnostic message', 'trace')
        ->not->toContain('hidden-password', 'hidden-api-key', 'hidden-cookie', '"args"');
});

test('cli exceptions are reported to actual stderr and daily files without http context', function () {
    $code = <<<'PHP'
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(["logging.default" => "stack", "logging.channels.stack.channels" => ["daily", "stderr"], "logging.channels.daily.path" => $argv[1]."/cli.log", "logging.channels.stderr.handler_with.stream" => "php://stderr"]);
    config(["services.diagnostic.secret" => "private-cli-secret"]);
    Illuminate\Support\Facades\Log::forgetChannel("stack");
    report(new RuntimeException("Controlled command error private-cli-secret"));
    PHP;
    $process = new Process([PHP_BINARY, '-r', $code, $this->logDirectory], base_path());
    $process->mustRun();
    expect($process->getErrorOutput())->toContain('RuntimeException', 'Controlled command error', 'trace')
        ->not->toContain('http_method', 'route_name', 'private-cli-secret');
    expect(File::get($this->logDirectory.'/cli-'.now()->format('Y-m-d').'.log'))->toContain('RuntimeException', 'Controlled command error');
});

test('numeric request secrets are redacted from exception messages', function () {
    Route::post('/__test/numeric-secret', function (Request $request) {
        throw new RuntimeException('Numeric credential '.$request->input('token'));
    });
    $this->postJson('/__test/numeric-secret', ['token' => 987654321])->assertStatus(500);
    expect(File::get($this->logDirectory.'/stderr.log'))->toContain('Numeric credential [REDACTED]')
        ->not->toContain('987654321');
});

test('production logging deployment is persistent rotated and debug safe', function () {
    expect(config('logging.channels.stack.ignore_exceptions'))->toBeTrue()
        ->and(config('logging.channels.daily.driver'))->toBe('daily')
        ->and(config('logging.channels.daily.days'))->toBeGreaterThan(0);
    $deployment = File::get(base_path('docker-compose.production.yml'));
    expect($deployment)->toContain("APP_DEBUG: 'false'", 'LOG_STACK: daily,stderr', 'soamco_budget_logs:/var/www/html/storage/logs', 'chmod 2775');
    expect(File::get(base_path('docker/php/Dockerfile')))->toContain('ENTRYPOINT ["production-entrypoint"]');
    expect(File::get(base_path('docker/php/production-entrypoint')))->toContain('config:cache', 'event:cache', 'route:cache', 'view:cache');
});

test('queue and scheduler failures keep operational context without duplicate reports', function () {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\DiagnosticJob');
    $job->shouldReceive('getJobId')->andReturn('diagnostic-job-id');
    $job->shouldReceive('getQueue')->andReturn('diagnostic-queue');
    $job->shouldReceive('attempts')->andReturn(2);
    $exception = new RuntimeException('Controlled queue failure');
    Event::dispatch(new JobExceptionOccurred('redis', $job, $exception));
    report($exception);
    $task = app(Schedule::class)->call(fn () => null)->name('diagnostic-schedule');
    $scheduledException = new RuntimeException('Controlled scheduled failure');
    Event::dispatch(new ScheduledTaskFailed($task, $scheduledException));
    report($scheduledException);
    Log::error('After operational failure');
    $contents = File::get($this->logDirectory.'/stderr.log');
    expect($contents)->toContain('App\\\\Jobs\\\\DiagnosticJob', 'diagnostic-job-id', 'diagnostic-queue', 'diagnostic-schedule')
        ->and(substr_count($contents, 'Controlled queue failure'))->toBe(2)
        ->and(substr_count($contents, 'Controlled scheduled failure'))->toBe(2);
    $last = collect(explode("\n", trim($contents)))->last();
    expect($last)->not->toContain('diagnostic-job-id', 'diagnostic-schedule');
});
