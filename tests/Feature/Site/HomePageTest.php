<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the marketing homepage with the dashboard link', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('site/home')
            ->where('dashboardUrl', route('client.dashboard'))
        );
});

it('shows the site not-found page for unknown URLs', function () {
    $this->get('/nope')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('site/errors/not-found'));
});
