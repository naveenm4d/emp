<?php

use App\Domains\Notification\Contracts\NotificationServiceInterface;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Jobs\SendNotificationJob;
use App\Domains\Notification\Models\Notification;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.whatsapp.api_token' => 'token',
        'services.whatsapp.phone_number_id' => '123',
        'services.whatsapp.graph_url' => 'https://graph.test/v19.0',
    ]);
});

it('sends through the WhatsApp Cloud API', function () {
    Http::fake(['graph.test/*' => Http::response(['messages' => [['id' => 'wamid.abc']]])]);
    $notification = Notification::factory()->create(['recipient' => '+15550100200']);

    app(NotificationServiceInterface::class)->deliver($notification->id);

    expect($notification->fresh())
        ->status->toBe(NotificationStatus::Sent)
        ->provider_message_id->toBe('wamid.abc')
        ->attempts->toBe(1);

    Http::assertSent(fn ($request) => $request['to'] === '15550100200' && $request->hasHeader('Authorization', 'Bearer token'));
});

it('records the provider error and rethrows so the queue retries', function () {
    Http::fake(['graph.test/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400)]);
    $notification = Notification::factory()->create();

    expect(fn () => app(NotificationServiceInterface::class)->deliver($notification->id))
        ->toThrow(RuntimeException::class, 'Invalid parameter');

    expect($notification->fresh())
        ->status->toBe(NotificationStatus::Pending)
        ->error->toContain('Invalid parameter');
});

it('marks the notification failed once the job gives up', function () {
    $notification = Notification::factory()->create();

    (new SendNotificationJob($notification->id))->failed(new RuntimeException('boom'));

    expect($notification->fresh())->status->toBe(NotificationStatus::Failed)->error->toBe('boom');
});

it('skips notifications that are no longer pending', function () {
    Http::fake();
    $notification = Notification::factory()->create(['status' => NotificationStatus::Sent]);

    app(NotificationServiceInterface::class)->deliver($notification->id);

    Http::assertNothingSent();
});
