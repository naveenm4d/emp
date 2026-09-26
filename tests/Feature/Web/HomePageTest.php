<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the marketing homepage with the dashboard link', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('web/home')
            ->where('dashboardUrl', route('client.dashboard'))
        );
});

it('shows the web not-found page for unknown URLs', function () {
    $this->get('/nope')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('web/errors/not-found'));
});
