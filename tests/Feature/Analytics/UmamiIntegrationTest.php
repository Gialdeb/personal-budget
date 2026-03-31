<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the umami script only when the integration is enabled and configured', function () {
    config()->set('analytics.umami.enabled', true);
    config()->set('analytics.umami.script_url', 'http://localhost:3001/script.js');
    config()->set('analytics.umami.website_id', 'website-123');
    config()->set('analytics.umami.domains', ['soamco.lo', 'www.soamco.lo']);
    config()->set('analytics.umami.environment_tag', 'local');
    config()->set('analytics.umami.respect_dnt', true);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('src="http://localhost:3001/script.js"', false)
        ->assertSee('data-website-id="website-123"', false)
        ->assertSee('data-auto-track="false"', false)
        ->assertSee('data-domains="soamco.lo,www.soamco.lo"', false)
        ->assertSee('data-tag="local"', false)
        ->assertSee('data-do-not-track="true"', false);
});

it('does not render the umami script when the integration is disabled', function () {
    config()->set('analytics.umami.enabled', false);
    config()->set('analytics.umami.script_url', 'http://localhost:3001/script.js');
    config()->set('analytics.umami.website_id', 'website-123');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-website-id="website-123"', false)
        ->assertDontSee('data-auto-track="false"', false);
});

it('shares the umami analytics configuration with inertia', function () {
    config()->set('analytics.umami.enabled', true);
    config()->set('analytics.umami.host_url', 'http://localhost:3001');
    config()->set('analytics.umami.website_id', 'website-123');
    config()->set('analytics.umami.domains', ['soamco.lo']);
    config()->set('analytics.umami.environment_tag', 'local');
    config()->set('analytics.umami.respect_dnt', true);
    config()->set('analytics.umami.public_route_names', [
        'home',
        'features',
        'pricing',
        'about-me',
        'customers',
        'download-app',
        'changelog.index',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('canRegister', Route::has('register'))
            ->where('analytics.current_route_name', 'home')
            ->where('analytics.umami.enabled', true)
            ->where('analytics.umami.host_url', 'http://localhost:3001')
            ->where('analytics.umami.website_id', 'website-123')
            ->where('analytics.umami.domains.0', 'soamco.lo')
            ->where('analytics.umami.environment_tag', 'local')
            ->where('analytics.umami.respect_dnt', true)
            ->where('analytics.umami.public_route_names.6', 'changelog.index'));
});
