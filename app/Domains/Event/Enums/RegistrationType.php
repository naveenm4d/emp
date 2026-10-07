<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

/** Who can join an event: anyone with the public link, approved requests only, or the client's guest list only. */
enum RegistrationType: string
{
    use HasValues;

    // Listed in this order wherever the types are offered: the guest list first.
    case GuestListOnly = 'guest_list_only';
    case Open = 'open';
    case ApprovalRequired = 'approval_required';

    /** The public event page accepts self-registration (while registration is open). */
    public function hasPublicRegistration(): bool
    {
        return $this !== self::GuestListOnly;
    }

    /** Public registrations wait for the client's approval. */
    public function requiresApproval(): bool
    {
        return $this === self::ApprovalRequired;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::ApprovalRequired => 'Approval required',
            self::GuestListOnly => 'Guest list only',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Open => 'Anyone with the public link can register and join.',
            self::ApprovalRequired => 'Guests request to attend through the public link. You approve them before they are confirmed.',
            self::GuestListOnly => 'No public registration. Only guests you add can RSVP, through their personal link.',
        };
    }

    /**
     * What the type means, shown as a checklist when choosing it.
     *
     * @return list<array{label: string, value: string, ok: bool}>
     */
    public function details(): array
    {
        return match ($this) {
            self::Open => [
                ['label' => 'Public event link', 'value' => 'Yes', 'ok' => true],
                ['label' => 'Who can register', 'value' => 'Anyone with the link', 'ok' => true],
                ['label' => 'Public registrations', 'value' => 'Confirmed straight away', 'ok' => true],
                ['label' => 'Guests you add', 'value' => 'Approved', 'ok' => true],
                ['label' => 'Pause sign-ups', 'value' => 'Open / close registration on the overview', 'ok' => true],
            ],
            self::ApprovalRequired => [
                ['label' => 'Public event link', 'value' => 'Yes', 'ok' => true],
                ['label' => 'Who can register', 'value' => 'Anyone, as a request', 'ok' => true],
                ['label' => 'Public registrations', 'value' => 'Wait for your approval (approve, waitlist or reject)', 'ok' => true],
                ['label' => 'Guests you add', 'value' => 'Approved', 'ok' => true],
                ['label' => 'Pause sign-ups', 'value' => 'Open / close registration on the overview', 'ok' => true],
            ],
            self::GuestListOnly => [
                ['label' => 'Public event link', 'value' => 'No, it shows an "invited guests only" page', 'ok' => false],
                ['label' => 'Who can register', 'value' => 'Only guests you add', 'ok' => false],
                ['label' => 'Public registrations', 'value' => 'Not available', 'ok' => false],
                ['label' => 'Guests you add', 'value' => 'Approved, and RSVP through their personal link', 'ok' => true],
                ['label' => 'Pause sign-ups', 'value' => 'Not needed', 'ok' => false],
            ],
        };
    }

    /** @return list<array{value: string, label: string, description: string, details: list<array{label: string, value: string, ok: bool}>}> */
    public static function detailedOptions(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'details' => $type->details(),
        ], self::cases());
    }
}
