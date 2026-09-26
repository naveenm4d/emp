<?php

use App\Domains\Client\Models\Client;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(Client::factory()->create(), 'client');
});

function mapPreview(string $url)
{
    return test()->getJson('/app/map-preview?url='.urlencode($url));
}

it('embeds the location of a full Google Maps link', function (string $url, string $query) {
    mapPreview($url)->assertOk()->assertJsonPath('embed_url', "https://maps.google.com/maps?q={$query}&z=15&output=embed");
})->with([
    'pin coordinates' => ['https://www.google.com/maps/place/Galle+Face/@6.92,79.84,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d6.9271!4d79.8446', '6.9271%2C79.8446'],
    'search query' => ['https://www.google.com/maps/search/?api=1&query=Galle+Face+Hotel', 'Galle%20Face%20Hotel'],
    'place name' => ['https://maps.google.com/maps/place/Colombo+Lotus+Tower', 'Colombo%20Lotus%20Tower'],
    'viewport only' => ['https://www.google.com/maps/@6.9271,79.8446,15z', '6.9271%2C79.8446'],
]);

it('resolves Google short links on the server', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Galle+Face/data=!3d6.9271!4d79.8446']),
    ]);

    mapPreview('https://maps.app.goo.gl/AbC123')
        ->assertOk()
        ->assertJsonPath('embed_url', 'https://maps.google.com/maps?q=6.9271%2C79.8446&z=15&output=embed');
});

it('never requests links outside Google Maps', function () {
    Http::fake();

    mapPreview('https://example.com/maps/place/Somewhere')->assertOk()->assertJsonPath('embed_url', null);
    mapPreview('http://maps.app.goo.gl/AbC123')->assertOk()->assertJsonPath('embed_url', null);

    Http::assertNothingSent();
});

it('stops when a short link redirects away from Google', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
    ]);

    mapPreview('https://maps.app.goo.gl/Evil')->assertOk()->assertJsonPath('embed_url', null);

    Http::assertSentCount(1);
});
