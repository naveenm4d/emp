<?php

namespace App\Domains\Event\Services;

use App\Core\Exceptions\NotFoundException;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use App\Domains\Guest\Models\Guest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use RuntimeException;

class EventLinkService implements EventLinkServiceInterface
{
    private const int MAX_ATTEMPTS = 5;

    /** User agents of apps that fetch a link to build its preview (WhatsApp, iMessage, social networks, bots). */
    private const string CRAWLER_PATTERN = '/whatsapp|facebookexternalhit|facebot|twitterbot|slackbot|telegrambot|discordbot|linkedinbot|skypeuripreview|applebot|googlebot|bingbot|embedly|bot\b|crawler|spider|preview/i';

    public function createForEvent(Event $event): EventLink
    {
        return EventLink::query()->where('event_id', $event->id)->whereNull('guest_id')->first()
            ?? $this->create(['event_id' => $event->id]);
    }

    public function createForGuest(Guest $guest): EventLink
    {
        return EventLink::query()->where('guest_id', $guest->id)->first()
            ?? $this->create(['event_id' => $guest->event_id, 'guest_id' => $guest->id]);
    }

    public function resolve(string $code): EventLink
    {
        return EventLink::query()
            ->where('code', $code)
            ->whereHas('event') // soft-deleted events are excluded by the scope
            ->with(['event', 'guest'])
            ->first()
            ?? throw new NotFoundException('Link not found.');
    }

    public function recordOpen(EventLink $link, Request $request): void
    {
        if (preg_match(self::CRAWLER_PATTERN, (string) $request->userAgent())) {
            return;
        }

        EventLink::query()->whereKey($link->id)->update([
            'open_count' => $link->open_count + 1,
            'last_opened_at' => now(),
        ]);
    }

    /** @param  array<string, string>  $attributes */
    private function create(array $attributes): EventLink
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return EventLink::query()->create([...$attributes, 'code' => EventLink::generateCode()]);
            } catch (UniqueConstraintViolationException $e) {
                // A code collision retries with a new code; a second link for the same guest/event is a race.
                if (! str_contains($e->getMessage(), 'event_links_code_unique')) {
                    return isset($attributes['guest_id'])
                        ? EventLink::query()->where('guest_id', $attributes['guest_id'])->firstOrFail()
                        : EventLink::query()->where('event_id', $attributes['event_id'])->whereNull('guest_id')->firstOrFail();
                }
            }
        }

        throw new RuntimeException('Could not generate a unique link code.');
    }
}
