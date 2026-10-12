<?php

use App\Models\AdminInvitation;
use App\Models\User;
use App\Services\Admin\AdminInvitationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class);

function concurrentAdminInvitationConsumption(string $databasePath, int $targetId, int $invitationId, float $startAt): Closure
{
    return static function () use ($databasePath, $targetId, $invitationId, $startAt): string {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $databasePath, 'database.connections.sqlite.transaction_mode' => 'IMMEDIATE', 'database.connections.sqlite.busy_timeout' => 5000, 'broadcasting.default' => 'log', 'database.connections.sqlite.url' => null, 'cache.default' => 'array']);
        DB::purge('sqlite');
        $remaining = $startAt - microtime(true);
        if ($remaining > 0) {
            usleep((int) ($remaining * 1000000));
        }
        try {
            app(AdminInvitationService::class)->accept(User::findOrFail($targetId), AdminInvitation::findOrFail($invitationId), '123456');

            return 'accepted';
        } catch (ValidationException) {
            return 'rejected';
        }
    };
}

test('two processes cannot consume the same administrator invitation twice', function () {
    $databasePath = tempnam(sys_get_temp_dir(), 'soamco-admin-race-');
    $originalConnection = config('database.default');
    $originalSqlite = config('database.connections.sqlite');
    try {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $databasePath, 'database.connections.sqlite.transaction_mode' => 'IMMEDIATE', 'database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create();
        $invitation = AdminInvitation::factory()->create(['target_user_id' => $target->id, 'invited_by_user_id' => $admin->id]);
        $targetId = $target->id;
        $invitationId = $invitation->id;
        $startAt = microtime(true) + 1;
        $consume = concurrentAdminInvitationConsumption($databasePath, $targetId, $invitationId, $startAt);
        $results = Concurrency::driver('process')->run([$consume, $consume]);
        sort($results);
        expect($results)->toBe(['accepted', 'rejected'])
            ->and($target->fresh()->hasRole('admin'))->toBeTrue()
            ->and(Activity::where('description', 'admin.invitation.accepted')->count())->toBe(1);
    } finally {
        DB::purge('sqlite');
        config(['database.default' => $originalConnection, 'database.connections.sqlite' => $originalSqlite]);
        unlink($databasePath);
    }
});
