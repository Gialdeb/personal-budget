<?php

use App\Jobs\DeliverSupportTicketMessage;
use App\Mail\SupportRequestSubmittedMail;
use App\Models\ChangelogRelease;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Push\PushNotificationService;
use App\Services\Support\StoreSupportRequest;
use App\Services\Support\SupportTicketService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    ChangelogRelease::factory()->published()->create(['version_label' => '2.0.0-beta.1', 'version_major' => 2, 'version_minor' => 0, 'version_patch' => 0]);
    ChangelogRelease::factory()->create(['version_label' => '9.0.0-draft', 'version_major' => 9]);
    Bus::fake([DeliverSupportTicketMessage::class]);
    Mail::fake();
    $this->owner = User::factory()->create(['locale' => 'it']);
    $this->owner->assignRole('user');
    $this->admin = User::factory()->create(['name' => 'Secret Admin', 'email' => 'private-admin@example.test']);
    $this->admin->assignRole('admin');
});

function conversationTicket(User $owner, string $category = 'bug'): SupportRequest
{
    return app(StoreSupportRequest::class)->handle($owner, ['category' => $category, 'subject' => 'Ticket di verifica', 'message' => 'Messaggio iniziale abbastanza lungo per la verifica.']);
}

it('opens each ticket type with the server version and a persisted first message', function (string $category) {
    config(['app.version' => '2.4.0-beta']);
    $this->actingAs($this->owner)->post(route('support.requests.store'), ['category' => $category, 'subject' => 'Version test', 'message' => 'Questo messaggio descrive il problema da verificare.', 'app_version' => 'spoofed'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $ticket = SupportRequest::sole();
    expect($ticket->category)->toBe($category)->and($ticket->app_version)->toBe('2.0.0-beta.1')->and($ticket->status)->toBe('waiting_admin')->and($ticket->messages()->sole()->body)->toBe($ticket->message)->and($ticket->last_user_message_at)->not->toBeNull();
    Bus::assertDispatched(DeliverSupportTicketMessage::class);
})->with(['bug', 'feature_request', 'general_support']);

it('allows multiple active tickets but blocks consecutive user messages in the same ticket', function () {
    $ticket = conversationTicket($this->owner);
    $this->actingAs($this->owner)->post(route('support.requests.store'), ['category' => 'bug', 'subject' => 'Duplicate', 'message' => 'Tentativo di apertura di una nuova richiesta.'])->assertSessionHasNoErrors();
    $this->post(route('support.requests.reply', $ticket->uuid), ['body' => 'Secondo messaggio'])->assertSessionHasErrors('body');
    expect(SupportRequest::count())->toBe(2)->and($ticket->messages()->count())->toBe(1);
});

it('alternates replies and preserves an anonymous user payload while auditing the admin', function () {
    $ticket = conversationTicket($this->owner);
    $this->actingAs($this->admin)->post(route('admin.support-requests.reply', $ticket->uuid), ['body' => 'Risposta del supporto'])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($ticket->fresh()->status)->toBe('waiting_user')->and($ticket->fresh()->last_admin_reply_at)->not->toBeNull()->and($ticket->messages()->where('author_type', 'admin')->sole()->admin_id)->toBe($this->admin->id);
    $this->actingAs($this->owner)->get(route('support.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('currentTicket.messages.1.body', 'Risposta del supporto')->missing('currentTicket.messages.1.admin_id')->missing('currentTicket.messages.1.admin_name')->missing('currentTicket.messages.1.admin')->where('currentTicket.status', 'waiting_user'));
    $payload = app(SupportTicketService::class)->userPayload($ticket->fresh()->load('messages'));
    expect(json_encode($payload))->not->toContain($this->admin->email)->not->toContain($this->admin->name);
    $this->actingAs($this->owner)->post(route('support.requests.reply', $ticket->uuid), ['body' => 'Grazie, ho ulteriori dettagli.'])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($ticket->fresh()->status)->toBe('waiting_admin')->and($ticket->messages()->count())->toBe(3);
    $this->post(route('support.requests.reply', $ticket->uuid), ['body' => 'Duplicato'])->assertSessionHasErrors('body');
    expect($ticket->messages()->count())->toBe(3);
});

it('delivers database notifications and the existing admin email once per user message', function () {
    $ticket = conversationTicket($this->owner, 'feature_request');
    $job = new DeliverSupportTicketMessage($ticket->messages()->sole()->id);
    $job->handle(app(PushNotificationService::class));
    $job->handle(app(PushNotificationService::class));
    $html = (new SupportRequestSubmittedMail($ticket, $ticket->message))->render();
    expect($html)->toContain($ticket->app_version)->toContain(route('admin.support-requests.show', $ticket->uuid))->toContain($ticket->message)->not->toContain('waiting_admin');
    expect($this->admin->notifications()->count())->toBe(1);
    $data = $this->admin->notifications()->sole()->data;
    expect($data['content']['message'])->toContain('funzionalità')->and($data['content']['cta_url'])->toContain($ticket->uuid);
    Mail::assertSentCount(1);
    Mail::assertSent(SupportRequestSubmittedMail::class, fn ($mail) => $mail->hasTo($this->admin->email) && $mail->supportRequest->app_version === '2.0.0-beta.1');
    app(SupportTicketService::class)->reply($this->admin, $ticket, 'Risposta assistenza', true);
    app(SupportTicketService::class)->reply($this->owner, $ticket, 'Replica utente');
    (new DeliverSupportTicketMessage($ticket->messages()->where('author_type', 'user')->reorder()->latest('id')->firstOrFail()->id))->handle(app(PushNotificationService::class));
    expect($this->admin->notifications()->count())->toBe(2);
    Mail::assertSentCount(2);
});

it('notifies the user in app and via existing push without email and without admin identity', function () {
    config(['features.push_notifications.enabled' => true]);
    $ticket = conversationTicket($this->owner);
    app(SupportTicketService::class)->reply($this->admin, $ticket, 'Risposta privata admin', true);
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->withArgs(fn ($user, $title, $body, $url, $data) => $user->is($this->owner) && str_contains($url, $ticket->uuid) && ! str_contains($body, 'Secret Admin'))->andReturn(['success' => 1]);
    $job = new DeliverSupportTicketMessage($ticket->messages()->where('author_type', 'admin')->sole()->id);
    $job->handle($push);
    $job->handle($push);
    expect($this->owner->notifications()->count())->toBe(1)->and(json_encode($this->owner->notifications()->sole()->data))->not->toContain($this->admin->name)->not->toContain($this->admin->email);
    Mail::assertNothingSent();
});

it('closes and reopens tickets with audit history alongside another active ticket', function () {
    $ticket = conversationTicket($this->owner);
    $this->actingAs($this->admin)->patch(route('admin.support-requests.update', $ticket->uuid), ['status' => 'closed'])->assertSessionHasNoErrors();
    expect($ticket->fresh()->closed_at)->not->toBeNull()->and($ticket->messages()->where('author_type', 'system')->sole()->body)->toBe('closed');
    $this->actingAs($this->owner)->post(route('support.requests.reply', $ticket->uuid), ['body' => 'Non consentito'])->assertSessionHasErrors('body');
    $new = conversationTicket($this->owner);
    $this->actingAs($this->admin)->patch(route('admin.support-requests.update', $ticket->uuid), ['status' => 'waiting_admin'])->assertSessionHasNoErrors();
    app(SupportTicketService::class)->changeStatus($this->admin, $new, 'closed');
    $this->patch(route('admin.support-requests.update', $ticket->uuid), ['status' => 'waiting_admin'])->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe('waiting_admin')->and($ticket->fresh()->closed_at)->toBeNull();
    $this->patch(route('admin.support-requests.update', $ticket->uuid), ['status' => 'waiting_user'])->assertSessionHasErrors('status');
});

it('protects other user conversations and admin endpoints', function () {
    $ticket = conversationTicket($this->owner);
    $other = User::factory()->create();
    $other->assignRole('user');
    $this->actingAs($other)->get(route('support.index', ['ticket' => $ticket->uuid]))->assertNotFound();
    $this->post(route('support.requests.reply', $ticket->uuid), ['body' => 'Accesso non consentito'])->assertNotFound();
    $this->post(route('admin.support-requests.reply', $ticket->uuid), ['body' => 'Non admin'])->assertForbidden();
    $this->get(route('admin.support-requests.index'))->assertForbidden();
    expect($ticket->messages()->count())->toBe(1);
});

it('filters by user and version and sorts tickets needing a reply first', function () {
    $waiting = conversationTicket($this->owner);
    $waiting->update(['updated_at' => now()->subDay()]);
    $other = User::factory()->create(['name' => 'Needle Person']);
    $answered = SupportRequest::factory()->create(['user_id' => $other->id, 'status' => 'waiting_user', 'app_version' => 'old-version']);
    $this->actingAs($this->admin)->get(route('admin.support-requests.index'))->assertInertia(fn (Assert $page) => $page->where('supportRequests.data.0.uuid', $waiting->uuid));
    $this->get(route('admin.support-requests.index', ['search' => 'Needle', 'version' => 'old-version', 'status' => 'waiting_user']))->assertInertia(fn (Assert $page) => $page->has('supportRequests.data', 1)->where('supportRequests.data.0.uuid', $answered->uuid));
});

it('uses current locked state rather than a stale ticket model for duplicate requests', function () {
    $ticket = conversationTicket($this->owner);
    $stale = $ticket->replicate();
    $stale->id = $ticket->id;
    app(SupportTicketService::class)->reply($this->admin, $ticket, 'Prima risposta', true);
    expect(fn () => app(SupportTicketService::class)->reply($this->admin, $stale, 'Risposta duplicata', true))->toThrow(ValidationException::class);
    expect($ticket->messages()->count())->toBe(2);
});

it('keeps the in app reply exactly once when push throws and a queued retry succeeds', function () {
    config(['features.push_notifications.enabled' => true]);
    $ticket = conversationTicket($this->owner);
    app(SupportTicketService::class)->reply($this->admin, $ticket, 'Risposta assistenza', true);
    $message = $ticket->messages()->where('author_type', 'admin')->sole();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andThrow(new RuntimeException('Transient push failure'));
    $job = new DeliverSupportTicketMessage($message->id);
    expect(fn () => $job->handle($push))->toThrow(RuntimeException::class);
    expect($message->fresh()->push_sent_at)->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
    $retry = Mockery::mock(PushNotificationService::class);
    $retry->shouldReceive('sendToUser')->once()->andReturn(['sent_count' => 1]);
    $job->handle($retry);
    expect($message->fresh()->push_sent_at)->not->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('notifies every administrator when a ticket is opened', function () {
    $second = User::factory()->create();
    $second->assignRole('admin');
    $ticket = conversationTicket($this->owner);
    (new DeliverSupportTicketMessage($ticket->messages()->sole()->id))->handle(app(PushNotificationService::class));
    expect($this->admin->notifications()->count())->toBe(1)->and($second->notifications()->count())->toBe(1);
    Mail::assertSentCount(1);
});

it('backfills legacy requests without fabricating versions or resending notifications', function () {
    $migration = require database_path('migrations/2026_10_12_151022_add_conversation_to_support_requests.php');
    $inactivityMigration = require database_path('migrations/2026_10_12_160742_add_inactivity_deadlines_to_support_requests.php');
    $inactivityMigration->down();
    $migration->down();
    $uuid = (string) Str::uuid();
    DB::table('support_requests')->insert(['uuid' => $uuid, 'user_id' => $this->owner->id, 'category' => 'bug', 'subject' => 'Legacy', 'message' => 'Original legacy text', 'locale' => 'it', 'status' => 'new', 'created_at' => now()->subYear(), 'updated_at' => now()->subYear()]);
    $migration->up();
    $inactivityMigration->up();
    $ticket = SupportRequest::where('uuid', $uuid)->sole();
    expect($ticket->status)->toBe('waiting_admin')->and($ticket->app_version)->toBeNull()->and($ticket->messages()->sole()->body)->toBe('Original legacy text')->and($ticket->messages()->sole()->admin_notified_at)->not->toBeNull();
});

it('respects the existing user push opt out while preserving the in app notification', function () {
    config(['features.push_notifications.enabled' => true]);
    $this->owner->settings()->updateOrCreate([], ['settings' => ['notifications' => ['push' => ['enabled' => false]]]]);
    $ticket = conversationTicket($this->owner);
    app(SupportTicketService::class)->reply($this->admin, $ticket, 'Risposta assistenza', true);
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldNotReceive('sendToUser');
    (new DeliverSupportTicketMessage($ticket->messages()->where('author_type', 'admin')->sole()->id))->handle($push);
    expect($this->owner->notifications()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('retries an unsuccessful push response instead of marking it delivered', function () {
    config(['features.push_notifications.enabled' => true]);
    $ticket = conversationTicket($this->owner);
    app(SupportTicketService::class)->reply($this->admin, $ticket, 'Risposta assistenza', true);
    $message = $ticket->messages()->where('author_type', 'admin')->sole();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andReturn(['sent_count' => 0, 'failed_count' => 1, 'invalidated_count' => 0]);
    expect(fn () => (new DeliverSupportTicketMessage($message->id))->handle($push))->toThrow(RuntimeException::class);
    expect($message->fresh()->push_sent_at)->toBeNull()->and($this->owner->notifications()->count())->toBe(1);
});

it('paginates the compact user history and rejects malformed ticket links', function () {
    SupportRequest::factory()->count(11)->create(['user_id' => $this->owner->id, 'status' => 'closed']);
    $this->actingAs($this->owner)->get(route('support.index'))->assertInertia(fn (Assert $page) => $page->has('ticketHistory', 10)->where('hasActiveTicket', false)->where('historyNextUrl', fn ($url) => str_contains($url, 'page=2')));
    $this->get(route('support.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('ticketHistory', 1));
    $this->getJson(route('support.index', ['ticket' => 'invalid-uuid']))->assertUnprocessable()->assertJsonValidationErrors('ticket');
});

it('shows the database version and keeps the opening snapshot after a new release', function () {
    $ticket = conversationTicket($this->owner);
    ChangelogRelease::factory()->published()->create(['version_label' => '3.0.0-beta', 'version_major' => 3, 'version_minor' => 0, 'version_patch' => 0]);
    $this->actingAs($this->owner)->get(route('support.index'))->assertInertia(fn (Assert $page) => $page->where('appVersion', '3.0.0-beta')->where('currentTicket.app_version', '2.0.0-beta.1'));
    expect($ticket->fresh()->app_version)->toBe('2.0.0-beta.1');
});

it('counts all open tickets by category for the owner across pages excluding closed tickets', function () {
    SupportRequest::factory()->count(12)->create(['user_id' => $this->owner->id, 'category' => 'bug', 'status' => 'waiting_admin']);
    SupportRequest::factory()->count(2)->create(['user_id' => $this->owner->id, 'category' => 'feature_request', 'status' => 'waiting_user']);
    SupportRequest::factory()->create(['user_id' => $this->owner->id, 'category' => 'general_support', 'status' => 'waiting_admin']);
    SupportRequest::factory()->create(['user_id' => $this->owner->id, 'category' => 'bug', 'status' => 'closed']);
    SupportRequest::factory()->create(['category' => 'bug', 'status' => 'waiting_admin']);
    foreach ([1, 2] as $pageNumber) {
        $this->actingAs($this->owner)->get(route('support.index', ['page' => $pageNumber]))->assertInertia(fn (Assert $page) => $page->where('openTicketCounts.bug', 12)->where('openTicketCounts.feature_request', 2)->where('openTicketCounts.general_support', 1));
    }
});

it('selects the newest active ticket deterministically when opening times match', function () {
    $first = conversationTicket($this->owner);
    $second = conversationTicket($this->owner);
    $second->update(['created_at' => $first->created_at]);
    $this->actingAs($this->owner)->get(route('support.index'))->assertInertia(fn (Assert $page) => $page->where('currentTicket.uuid', $second->uuid)->has('ticketHistory', 2)->where('openTicketCounts.bug', 2));
});
