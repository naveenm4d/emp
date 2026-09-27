<?php

beforeEach(function () {
    $this->withoutVite();
});

dataset('dashboard pages', [
    'client' => fn () => route('client.login'),
    'admin' => fn () => route('admin.login'),
]);

it('renders dashboards in light mode by default', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('data-appearance="light"', false)
        ->assertDontSee('class="dark"', false);
})->with('dashboard pages');

it('renders dashboards dark from the unencrypted appearance cookie', function (string $url) {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get($url)
        ->assertOk()
        ->assertSee('data-appearance="dark"', false)
        ->assertSee('class="dark"', false);
})->with('dashboard pages');

it('resolves the system appearance on the device before first paint', function (string $url) {
    $this->withUnencryptedCookie('appearance', 'system')
        ->get($url)
        ->assertOk()
        ->assertSee('data-appearance="system"', false)
        ->assertSee("matchMedia('(prefers-color-scheme: dark)')", false)
        ->assertDontSee('class="dark"', false);
})->with('dashboard pages');

it('ignores unknown appearance values', function () {
    $this->withUnencryptedCookie('appearance', 'neon')
        ->get(route('client.login'))
        ->assertOk()
        ->assertSee('data-appearance="light"', false);
});

it('leaves guest-facing pages unthemed', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('web.home'))
        ->assertOk()
        ->assertDontSee('data-appearance', false);
});
