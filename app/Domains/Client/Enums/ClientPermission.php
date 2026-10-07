<?php

namespace App\Domains\Client\Enums;

use App\Core\Enums\Concerns\HasValues;

/**
 * What a member of a client account may do. Viewing the events a member was
 * given needs no permission; the owner has them all.
 */
enum ClientPermission: string
{
    use HasValues;

    /** Create events; a member gets access to the events they create. */
    case EventsCreate = 'events.create';
    /** Event details, RSVP form, invitation design, publishing and registration. */
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    /** Add, edit and remove guests, and approve registrations. */
    case GuestsManage = 'guests.manage';
    /** Send RSVP links and reminders. */
    case MessagesSend = 'messages.send';
    /** Tables, seats and the floor plan. */
    case SeatingManage = 'seating.manage';
    /** Invite users, change their access and remove them. */
    case TeamManage = 'team.manage';

    public function label(): string
    {
        return match ($this) {
            self::EventsCreate => 'Create events',
            self::EventsUpdate => 'Edit events',
            self::EventsDelete => 'Delete events',
            self::GuestsManage => 'Manage guests',
            self::MessagesSend => 'Send messages',
            self::SeatingManage => 'Manage seating',
            self::TeamManage => 'Manage team',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EventsCreate => 'Start new events. They get access to the events they create.',
            self::EventsUpdate => 'Change details, the RSVP form and the invitation, and publish.',
            self::EventsDelete => 'Delete events they can see.',
            self::GuestsManage => 'Add, edit and remove guests, and approve registrations.',
            self::MessagesSend => 'Send RSVP links and reminders over WhatsApp.',
            self::SeatingManage => 'Arrange tables, seats and the floor plan.',
            self::TeamManage => 'Invite people, change their access and remove them.',
        };
    }

    /** @return list<array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label(), 'description' => $case->description()],
            self::cases(),
        );
    }
}
