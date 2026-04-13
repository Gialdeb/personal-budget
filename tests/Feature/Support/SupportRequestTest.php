<?php

use App\Mail\SupportRequestSubmittedMail;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function supportUser(array $attributes = []): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locale' => 'en',
        ...$attributes,
    ]);
    $user->assignRole('user');

    return $user;
}

function supportPayload(array $overrides = []): array
{
    return array_replace([
        'category' => SupportRequest::CATEGORY_BUG,
        'subject' => 'Unexpected issue while importing transactions',
        'message' => 'I found an error while importing transactions and the page stopped responding after the preview step.',
        'source_url' => '/help-center/articles/import-transactions',
        'source_route' => 'help-center.articles.show',
    ], $overrides);
}

it('renders the authenticated support page', function () {
    $internalAdmin = User::factory()->create([
        'id' => 1,
        'email' => 'admin@example.com',
        'name' => 'Primary',
        'surname' => 'Admin',
    ]);
    $internalAdmin->assignRole('admin');

    $user = supportUser();

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk()
        ->assertDontSee('admin@example.com')
        ->assertDontSee('Primary Admin')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Support')
            ->where('supportContext.locale', 'en')
            ->has('supportCategories', 3)
            ->missing('supportRecipient'));
});

it('redirects the legacy support route to settings support preserving context', function () {
    $user = supportUser();

    $this->actingAs($user)
        ->get('/support?source_url=%2Fhelp-center&source_route=help-center.index')
        ->assertRedirect(route('support.index', [
            'source_url' => '/help-center',
            'source_route' => 'help-center.index',
        ]));
});

it('allows an authenticated user to submit a support request and routes it internally to admin user 1', function () {
    Mail::fake();
    $internalAdmin = User::factory()->create([
        'id' => 1,
        'email' => 'admin@example.com',
        'name' => 'Primary',
        'surname' => 'Admin',
    ]);
    $internalAdmin->assignRole('admin');

    $user = supportUser(['locale' => 'it']);

    $this->actingAs($user)
        ->post(route('support.requests.store'), supportPayload())
        ->assertRedirect(route('support.index'))
        ->assertSessionHas('success');

    $supportRequest = SupportRequest::query()->firstOrFail();

    expect($supportRequest->user_id)->toBe($user->id)
        ->and($supportRequest->category)->toBe(SupportRequest::CATEGORY_BUG)
        ->and($supportRequest->status)->toBe(SupportRequest::STATUS_NEW)
        ->and($supportRequest->locale)->toBe('it')
        ->and($supportRequest->source_url)->toBe('/help-center/articles/import-transactions')
        ->and($supportRequest->source_route)->toBe('help-center.articles.show')
        ->and($supportRequest->meta)->toHaveKey('user_agent');

    Mail::assertSent(SupportRequestSubmittedMail::class, function (SupportRequestSubmittedMail $mail) use ($supportRequest, $internalAdmin): bool {
        return $mail->supportRequest->is($supportRequest)
            && $mail->hasTo($internalAdmin->email);
    });
});

it('does not allow guests to submit support requests', function () {
    $this->post(route('support.requests.store'), supportPayload())
        ->assertRedirect(route('login'));

    expect(SupportRequest::query()->count())->toBe(0);
});

it('validates required support request fields', function () {
    $user = supportUser();

    $this->actingAs($user)
        ->from(route('support.index'))
        ->post(route('support.requests.store'), supportPayload([
            'category' => 'invalid',
            'subject' => '',
            'message' => 'too short',
        ]))
        ->assertRedirect(route('support.index'))
        ->assertSessionHasErrors(['category', 'subject', 'message']);

    expect(SupportRequest::query()->count())->toBe(0);
});

it('stores feature request and general support categories correctly', function (string $category) {
    Mail::fake();
    $internalAdmin = User::factory()->create(['id' => 1]);
    $internalAdmin->assignRole('admin');
    $user = supportUser();

    $this->actingAs($user)
        ->post(route('support.requests.store'), supportPayload([
            'category' => $category,
        ]))
        ->assertRedirect(route('support.index'));

    expect(SupportRequest::query()->firstOrFail()->category)->toBe($category);
})->with([
    SupportRequest::CATEGORY_FEATURE_REQUEST,
    SupportRequest::CATEGORY_GENERAL_SUPPORT,
]);

it('reads source context from the support page query string', function () {
    $user = supportUser();

    $this->actingAs($user)
        ->get(route('support.index', [
            'source_url' => '/help-center',
            'source_route' => 'help-center.index',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('supportContext.source_url', '/help-center')
            ->where('supportContext.source_route', 'help-center.index'));
});
