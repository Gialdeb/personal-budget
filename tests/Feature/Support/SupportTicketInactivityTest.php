<?php

use App\Jobs\DeliverSupportTicketMessage;
use App\Jobs\WarnInactiveSupportTicket;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Push\PushNotificationService;
use App\Services\Support\SupportTicketInactivityService;
use App\Services\Support\SupportTicketService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Bus::fake([DeliverSupportTicketMessage::class, WarnInactiveSupportTicket::class]);
    $this->owner = User::factory()->create(['locale' => 'it']);
    $this->owner->assignRole('user');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->ticket = SupportRequest::factory()->for($this->owner)->create(['status' => SupportRequest::STATUS_NEW]);
    app(SupportTicketService::class)->reply($this->admin, $this->ticket, 'Risposta assistenza', true);
    $this->messageId = $this->ticket->messages()->where('author_type', 'admin')->sole()->id;
    config(['features.push_notifications.enabled' => false]);
});

it('warns on day two once and closes only after a full day of notice', function () {
    $service = app(SupportTicketInactivityService::class);
    $service->process();
    Bus::assertNotDispatched(WarnInactiveSupportTicket::class);
    $this->travel(2)->days();
    $service->process();
    Bus::assertDispatched(WarnInactiveSupportTicket::class);
    $job = new WarnInactiveSupportTicket($this->ticket->id, $this->messageId);
    $job->handle(app(PushNotificationService::class));
    $job->handle(app(PushNotificationService::class));
    expect($this->owner->notifications()->count())->toBe(1)
        ->and($this->ticket->fresh()->inactivity_warned_at)->not->toBeNull()
        ->and($this->ticket->messages()->where('body', 'inactivity_warning')->count())->toBe(1);
    $this->actingAs($this->owner)->get(route('support.index', ['ticket' => $this->ticket->uuid]))->assertInertia(fn (Assert $page) => $page->where('currentTicket.auto_close_at', $this->ticket->fresh()->auto_close_at->toIso8601String()));
    $this->travel(23)->hours();
    $service->process();
    expect($this->ticket->fresh()->status)->toBe('waiting_user');
    $this->travel(61)->minutes();
    $service->process();
    $service->process();
    expect($this->ticket->fresh()->status)->toBe('closed')->and($this->ticket->messages()->where('body', 'auto_closed')->count())->toBe(1);
    foreach ([$this->owner, $this->admin] as $actor) {
        $route = $actor->id === $this->owner->id ? 'support.requests.reply' : 'admin.support-requests.reply';
        $this->actingAs($actor)->post(route($route, $this->ticket->uuid), ['body' => 'Replica tardiva'])->assertSessionHasErrors('body');
    }
});

it('cancels pending warnings and closure when the user replies and resets a new cycle', function () {
    $this->travel(2)->days();
    $stale = new WarnInactiveSupportTicket($this->ticket->id, $this->messageId);
    $stale->handle(app(PushNotificationService::class));
    app(SupportTicketService::class)->reply($this->owner, $this->ticket, 'Nuovi dettagli');
    expect($this->ticket->fresh()->auto_close_at)->toBeNull();
    $this->travel(2)->days();
    app(SupportTicketInactivityService::class)->process();
    expect($this->ticket->fresh()->status)->toBe('waiting_admin');
    app(SupportTicketService::class)->reply($this->admin, $this->ticket, 'Nuova risposta', true);
    $stale->handle(app(PushNotificationService::class));
    expect($this->ticket->fresh()->inactivity_warned_at)->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
});

it('does not warn manually closed tickets and grants a day when warning delivery is delayed', function () {
    $this->travel(5)->days();
    $job = new WarnInactiveSupportTicket($this->ticket->id, $this->messageId);
    $job->handle(app(PushNotificationService::class));
    app(SupportTicketInactivityService::class)->process();
    expect($this->ticket->fresh()->status)->toBe('waiting_user');
    app(SupportTicketService::class)->changeStatus($this->admin, $this->ticket, 'closed');
    $job->handle(app(PushNotificationService::class));
    expect($this->ticket->fresh()->auto_close_at)->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
});

it('offers only ticket owners and recorded versions with open type counts and combinable filters', function () {
    $unused = User::factory()->create();
    $this->ticket->update(['app_version' => '2.0.0-beta.1']);
    SupportRequest::factory()->for($this->owner)->create(['category' => 'bug', 'status' => 'closed', 'app_version' => 'older']);
    $this->actingAs($this->admin)->get(route('admin.support-requests.index', ['user' => $this->owner->uuid, 'version' => '2.0.0-beta.1', 'category' => $this->ticket->category]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.status', 'open')->where('filters.user', $this->owner->uuid)
        ->where('supportRequests.meta.total', 1)->has('options.users', 1)->has('options.versions', 2)
        ->where('options.users.0.value', $this->owner->uuid)->where('options.openCounts.'.$this->ticket->category, 1));
    $this->get(route('admin.support-requests.index', ['status' => 'closed']))->assertInertia(fn (Assert $page) => $page->where('supportRequests.meta.total', 1));
    $this->getJson(route('admin.support-requests.index', ['user' => 'invalid']))->assertUnprocessable();
});

it('retries a failed push without setting the closing deadline and sends one successful warning', function () {
    config(['features.push_notifications.enabled' => true]);
    $this->travel(2)->days();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andReturn(['sent_count' => 0, 'failed_count' => 1, 'invalidated_count' => 0]);
    $job = new WarnInactiveSupportTicket($this->ticket->id, $this->messageId);
    expect(fn () => $job->handle($push))->toThrow(RuntimeException::class);
    expect($this->ticket->fresh()->auto_close_at)->toBeNull()->and($this->owner->notifications()->count())->toBe(0);
    $retry = Mockery::mock(PushNotificationService::class);
    $retry->shouldReceive('sendToUser')->once()->withArgs(fn ($user, $title, $body, $url) => $user->is($this->owner) && str_contains($body, 'domani') && str_contains($url, $this->ticket->uuid))->andReturn(['sent_count' => 1]);
    $job->handle($retry);
    $job->handle($retry);
    expect($this->ticket->fresh()->auto_close_at)->not->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
});

it('keeps the in app warning while respecting push opt out', function () {
    config(['features.push_notifications.enabled' => true]);
    $this->owner->settings()->updateOrCreate([], ['settings' => ['notifications' => ['push' => ['enabled' => false]]]]);
    $this->travel(2)->days();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldNotReceive('sendToUser');
    (new WarnInactiveSupportTicket($this->ticket->id, $this->messageId))->handle($push);
    expect($this->ticket->fresh()->inactivity_warned_at)->not->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
});
