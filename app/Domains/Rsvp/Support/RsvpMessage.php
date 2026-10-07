<?php

namespace App\Domains\Rsvp\Support;

use App\Domains\Rsvp\Enums\RsvpMessageKind;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Template\Support\PlaceholderFormat;

/**
 * The text sent to a guest with their RSVP link, as an invitation or a
 * reminder. The guest's own message wins over the event's, which wins over
 * DEFAULT / DEFAULT_REMINDER. Placeholders are written like
 * template ones ("{{ guest.name }}"); the link is appended when the text has
 * no {{ rsvp.link }}, so guests always get it.
 */
final class RsvpMessage
{
    public const string DEFAULT = "Hi {{ guest.name }}, you're invited to {{ event.title }}! Please RSVP here: {{ rsvp.link }}";

    public const string DEFAULT_REMINDER = 'Hi {{ guest.name }}, a friendly reminder to RSVP for {{ event.title }}: {{ rsvp.link }}';

    /** Email subjects (email only: WhatsApp and SMS have none); used when the event sets none. */
    public const string DEFAULT_SUBJECT = "You're invited to {{ event.title }}";

    public const string DEFAULT_REMINDER_SUBJECT = 'Reminder: please RSVP for {{ event.title }}';

    /** @var list<string> */
    public const array PLACEHOLDERS = ['guest.name', 'event.title', 'event.date', 'event.time', 'event.venue', 'rsvp.link'];

    public static function for(Rsvp $rsvp, RsvpMessageKind $kind = RsvpMessageKind::Invitation): string
    {
        $rsvp->loadMissing(['event', 'guest']);

        $text = match ($kind) {
            RsvpMessageKind::Invitation => $rsvp->guest->invitation_message ?: $rsvp->event->invitation_message ?: self::DEFAULT,
            RsvpMessageKind::Reminder => $rsvp->guest->reminder_message ?: $rsvp->event->reminder_message ?: self::DEFAULT_REMINDER,
        };

        $values = [
            'guest.name' => $rsvp->guest->name,
            'event.title' => $rsvp->event->title,
            'event.date' => PlaceholderFormat::date($rsvp->event->event_date),
            'event.time' => PlaceholderFormat::time($rsvp->event->start_time),
            'event.venue' => $rsvp->event->location_name,
            'rsvp.link' => $rsvp->rsvpUrl(),
        ];

        $hasLink = false;

        $message = (string) preg_replace_callback('/\{\{\s*([a-z.]+)\s*\}\}/', function (array $match) use ($values, &$hasLink): string {
            if (! array_key_exists($match[1], $values)) {
                return $match[0];
            }

            $hasLink = $hasLink || $match[1] === 'rsvp.link';

            return (string) $values[$match[1]];
        }, $text);

        return $hasLink ? $message : rtrim($message)."\n\n".$values['rsvp.link'];
    }
}
