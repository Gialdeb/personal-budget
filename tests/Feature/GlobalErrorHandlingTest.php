<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Opcodes\LogViewer\Facades\LogViewer;

beforeEach(function () {
    config(['app.debug' => false]);
    app()->setLocale('it');
    $this->withHeader('Accept-Language', 'it');
    Route::get('/__test/server-error', fn () => throw new RuntimeException('Private exception password=never-expose'))->name('test.server-error');
});

test('web 5xx shows safe soamco page and correlated reference', function () {
    $response = $this->get('/__test/server-error')->assertStatus(500);
    $id = $response->headers->get('X-Request-ID');
    expect(Str::isUuid($id))->toBeTrue();
    $response->assertSee('Si è verificato un problema')
        ->assertSee('L’operazione non è stata completata. Riprova tra poco.')
        ->assertSee(strtoupper(substr($id, 0, 8)))
        ->assertSee('Riprova')->assertSee('Torna indietro')->assertSee('Dashboard')
        ->assertDontSee('never-expose')->assertDontSee('RuntimeException');
});

test('json 5xx returns only generic message and correlation identifiers', function () {
    $response = $this->getJson('/__test/server-error')->assertStatus(500);
    $id = $response->headers->get('X-Request-ID');
    $response->assertExactJson([
        'message' => 'L’operazione non è stata completata. Riprova tra poco.',
        'request_id' => $id,
        'error_code' => strtoupper(substr($id, 0, 8)),
    ])->assertDontSee('never-expose');
});

test('inertia 5xx renders a page instead of the technical html modal', function () {
    $this->withHeader('X-Inertia', 'true')->get('/__test/server-error')->assertStatus(500)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.title', 'Si è verificato un problema')
        ->assertJsonPath('props.requestId', fn ($id) => Str::isUuid($id))
        ->assertJsonPath('props.dashboardUrl', route('dashboard'))
        ->assertDontSee('never-expose');
});

test('non server status codes retain their original contracts', function (int $status) {
    Route::get('/__test/status', fn () => abort($status, 'Original status message'));
    $this->getJson('/__test/status')->assertStatus($status)
        ->assertJsonPath('message', 'Original status message')
        ->assertJsonMissingPath('error_code');
})->with([401, 403, 404, 419, 422]);

test('inertia error page does not rerun failing shared business props', function () {
    Inertia::share('failingBusinessQuery', fn () => throw new RuntimeException('Do not run business query during error rendering'));
    $this->withHeader('X-Inertia', 'true')->get('/__test/server-error')->assertStatus(500)
        ->assertJsonPath('component', 'Error')->assertJsonMissingPath('props.failingBusinessQuery');
});

test('other server status codes retain their status', function () {
    Route::get('/__test/unavailable', fn () => abort(503, 'private backend detail'));
    $this->getJson('/__test/unavailable')->assertStatus(503)->assertDontSee('private backend detail');
});

test('debug environment keeps technical diagnostics available', function () {
    config(['app.debug' => true]);
    $this->getJson('/__test/server-error')->assertStatus(500)->assertJsonPath('exception', RuntimeException::class);
});

test('log viewer and data endpoints allow only authenticated administrators', function (string $role) {
    $this->seed(RolesAndPermissionsSeeder::class);
    if ($role !== 'guest') {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
    }
    $ui = $this->get(route('log-viewer.index'));
    $api = $this->getJson(route('log-viewer.files'));
    if ($role === 'admin') {
        $ui->assertOk()->assertSee('Log Viewer');
        $api->assertOk();
        foreach (['deleteLogFile', 'deleteLogFolder', 'downloadLogFile', 'downloadLogFolder'] as $ability) {
            expect(Gate::allows($ability))->toBeFalse();
        }
    } elseif ($role === 'guest') {
        $ui->assertRedirect(route('login'));
        $api->assertUnauthorized();
    } else {
        $ui->assertForbidden();
        $api->assertForbidden();
    }
})->with(['admin', 'user', 'guest']);

test('administrator can find actual sanitized exception stack by request id', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
    $path = storage_path('logs/verification-'.Str::uuid().'.log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    try {
        $response = $this->getJson('/__test/server-error');
        $id = $response->headers->get('X-Request-ID');
        $file = LogViewer::getFiles()->first(fn ($file) => $file->path === $path);
        expect($file)->not->toBeNull();
        $logs = $this->getJson(route('log-viewer.logs', ['file' => $file->identifier, 'query' => $id]))->assertOk();
        expect($logs->getContent())->toContain($id, 'RuntimeException', 'trace', 'GlobalErrorHandlingTest.php');
    } finally {
        Log::forgetChannel('single');
        File::delete($path);
    }
});

test('deployment maintenance retains its page and automatic refresh headers', function (bool $inertia) {
    $maintenance = Mockery::mock(MaintenanceMode::class);
    $maintenance->shouldReceive('active')->andReturn(true);
    $maintenance->shouldReceive('data')->andReturn(['retry' => 15, 'refresh' => 5]);
    app()->instance(MaintenanceMode::class, $maintenance);

    if ($inertia) {
        $this->withHeader('X-Inertia', 'true');
    }

    if ($inertia) {
        $this->get('/__test/server-error')->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/__test/server-error'));
        $this->withoutHeader('X-Inertia');
    }

    $this->get('/__test/server-error')->assertStatus(503)
        ->assertHeader('Retry-After', '15')
        ->assertHeader('Refresh', '5')
        ->assertSee('Stiamo aggiornando Soamco')
        ->assertDontSee('Si è verificato un problema')
        ->assertDontSee('never-expose');

})->with([false, true]);

test('maintenance page refreshes automatically even without deployment refresh option', function () {
    $maintenance = Mockery::mock(MaintenanceMode::class);
    $maintenance->shouldReceive('active')->andReturn(true);
    $maintenance->shouldReceive('data')->andReturn(['retry' => 15]);
    app()->instance(MaintenanceMode::class, $maintenance);

    $this->get('/__test/server-error')->assertStatus(503)
        ->assertHeader('Refresh', '10')
        ->assertSee('Stiamo aggiornando Soamco');
});
