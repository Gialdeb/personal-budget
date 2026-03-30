<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the public changelog index page', function () {
    $this->get(route('changelog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('changelog/Index')
            ->where('canRegister', Route::has('register')));
});

it('renders the public changelog detail page shell with version label', function () {
    $this->get(route('changelog.show', '0.10.4-beta'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('changelog/Show')
            ->where('versionLabel', '0.10.4-beta'));
});
