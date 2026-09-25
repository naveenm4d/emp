<?php

namespace App\Domains\Invitation\Services;

use App\Core\Exceptions\NotFoundException;
use App\Domains\Event\Models\Event;
use App\Domains\Invitation\Contracts\InvitationQueryServiceInterface;
use App\Domains\Invitation\Contracts\InvitationRepositoryInterface;
use App\Domains\Invitation\DTOs\InvitationFilters;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvitationQueryService implements InvitationQueryServiceInterface
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitations,
    ) {}

    public function forEvent(Event $event, InvitationFilters $filters): LengthAwarePaginator
    {
        return $this->invitations->paginateForEvent($event->id, $filters, config('emp.per_page'));
    }

    public function summary(Event $event): array
    {
        return $this->invitations->summary($event->id);
    }

    public function findByToken(string $token): Invitation
    {
        $invitation = $this->invitations->findByToken($token)
            ?? throw new NotFoundException('Invitation not found.');

        return $invitation->load(['event.templateVersion.template', 'event.media', 'guest']);
    }

    public function count(): int
    {
        return $this->invitations->count();
    }
}
