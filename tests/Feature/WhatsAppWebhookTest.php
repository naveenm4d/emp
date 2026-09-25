<?php

use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;

beforeEach(function () {
    config([
        'services.whatsapp.app_secret' => 'test-secret',
        'services.whatsapp.webhook_verify_token' => 'verify-me',
    ]);
});

function statusPayload(string $messageId, string $status): array
{
    return ['entry' => [['changes' => [['value' => ['statuses' => [
        ['id' => $messageId, 'status' => $status, 'timestamp' => (string) now()->timestamp],
    ]]]]]]];
}

function signedPost(array $payload, ?string $secret = 'test-secret')
{
    $body = json_encode($payload);

    return test()->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ], $body);
}

it('answers the Meta verification challenge', function () {
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
        ->assertOk()
        ->assertSeeText('12345');

    $this->getJson('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')
        ->assertForbidden();
});

it('rejects payloads with an invalid signature', function () {
    signedPost(statusPayload('wamid.1', 'delivered'), 'wrong-secret')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'Invalid signature.');
});

it('marks notifications delivered and logs the callback', function () {
    $notification = Notification::factory()->create([
        'status' => NotificationStatus::Sent,
        'provider_message_id' => 'wamid.1',
    ]);

    signedPost(statusPayload('wamid.1', 'delivered'))->assertOk()->assertJsonPath('status', 'received');

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Delivered)
        ->and($notification->delivered_at)->not->toBeNull()
        ->and($notification->deliveries()->count())->toBe(1);
});

it('acknowledges unknown message ids', function () {
    signedPost(statusPayload('wamid.unknown', 'delivered'))->assertOk();
});
