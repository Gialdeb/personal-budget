<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createInboxNotification(User $user, string $title, bool $read = false): string
{
    $uuid = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $uuid,
        'type' => 'manual-test',
        'data' => [
            'category' => [
                'key' => 'imports.completed',
                'name' => 'Import completato',
            ],
            'presentation' => [
                'layout' => 'standard_card',
                'icon' => 'import',
            ],
            'content' => [
                'title' => $title,
                'message' => 'Contenuto di test',
                'cta_label' => 'Apri',
                'cta_url' => '/imports',
            ],
        ],
        'read_at' => $read ? now() : null,
    ]);

    return $uuid;
}

test('shared notification inbox exposes unread count and latest notifications in the app shell', function () {
    $user = User::factory()->create();

    createInboxNotification($user, 'Import in arrivo');
    createInboxNotification($user, 'Report pronto');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('notificationInbox.unread_count', 2)
            ->has('notificationInbox.latest', 2)
            ->where('notificationInbox.latest.0.is_unread', true)
            ->where('notificationInbox.latest.0.uuid', fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->missing('notificationInbox.latest.0.id'));
});

test('notification preview endpoint returns the latest inbox items', function () {
    $user = User::factory()->create();

    createInboxNotification($user, 'Import completato');
    createInboxNotification($user, 'Report mensile pronto', true);

    $this->actingAs($user)
        ->get(route('notifications.preview'))
        ->assertOk()
        ->assertJson([
            'unread_count' => 1,
        ])
        ->assertJson(fn ($json) => $json
            ->etc()
            ->has('latest', 2)
            ->where('latest.0.content.title', fn (string $title) => in_array($title, ['Import completato', 'Report mensile pronto'], true))
            ->where('latest.1.content.title', fn (string $title) => in_array($title, ['Import completato', 'Report mensile pronto'], true))
            ->where('latest', fn ($latest) => collect($latest)->contains(
                fn (array $notification) => $notification['content']['title'] === 'Report mensile pronto'
                    && $notification['is_read'] === true
            ))
            ->where('latest', fn ($latest) => collect($latest)->contains(
                fn (array $notification) => $notification['content']['title'] === 'Import completato'
                    && $notification['is_unread'] === true
            )));
});

test('notification mark as read endpoint updates the unread count', function () {
    $user = User::factory()->create();
    $notificationUuid = createInboxNotification($user, 'Import completato');

    $this->actingAs($user)
        ->post(route('notifications.mark-as-read', ['notification' => $notificationUuid]))
        ->assertOk()
        ->assertJson([
            'unread_count' => 0,
        ]);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('notifications page renders the real inbox', function () {
    $user = User::factory()->create();

    createInboxNotification($user, 'Import completato');

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('summary.unread_count', 1)
            ->where('notifications.data.0.content.title', 'Import completato')
            ->missing('notifications.data.0.id'));
});

test('user can permanently delete one of their notifications', function () {
    $user = User::factory()->create();
    $notificationUuid = createInboxNotification($user, 'Import completato');

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->delete(route('notifications.destroy', [
            'notification' => $notificationUuid,
            'page' => 1,
        ]))
        ->assertRedirect(route('notifications.index', ['page' => 1]));

    $this->assertDatabaseMissing('notifications', ['id' => $notificationUuid]);
    expect($user->unreadNotifications()->count())->toBe(0);
});

test('user cannot delete another users notification', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notificationUuid = createInboxNotification($otherUser, 'Notifica privata');

    $this->actingAs($user)
        ->delete(route('notifications.destroy', ['notification' => $notificationUuid]))
        ->assertNotFound();

    $this->assertDatabaseHas('notifications', ['id' => $notificationUuid]);
});

test('deleting the only notification on the last page redirects to the previous valid page', function () {
    $user = User::factory()->create();

    foreach (range(1, 21) as $index) {
        createInboxNotification($user, "Notifica {$index}");
    }

    $notificationUuid = (string) $user->notifications()
        ->latest()
        ->skip(20)
        ->value('id');

    $this->actingAs($user)
        ->delete(route('notifications.destroy', [
            'notification' => $notificationUuid,
            'page' => 2,
        ]))
        ->assertRedirect(route('notifications.index', ['page' => 1]));

    expect($user->notifications()->count())->toBe(20);
});

test('notification pagination labels are localized', function (string $locale, string $previous, string $next) {
    $user = User::factory()->create(['locale' => $locale]);

    foreach (range(1, 21) as $index) {
        createInboxNotification($user, "Notifica {$index}");
    }

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.meta.links', function ($links) use ($previous, $next): bool {
                $labels = collect($links)->pluck('label');

                return $labels->contains(fn (string $label): bool => str_contains($label, $previous))
                    && $labels->contains(fn (string $label): bool => str_contains($label, $next))
                    && $labels->doesntContain(fn (string $label): bool => str_contains($label, 'pagination.'));
            }));
})->with([
    'Italian' => ['it', 'Precedente', 'Successiva'],
    'English' => ['en', 'Previous', 'Next'],
]);
