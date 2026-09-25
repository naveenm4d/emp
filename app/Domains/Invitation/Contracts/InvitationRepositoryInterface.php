<?php

namespace App\Domains\Invitation\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Invitation\DTOs\InvitationFilters;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Invitation>
 */
interface InvitationRepositoryInterface extends RepositoryInterface
{
    public function findByToken(string $token): ?Invitation;

    public function findActiveForGuest(string $guestId): ?Invitation;

    /** @return LengthAwarePaginator<int, Invitation> */
    public function paginateForEvent(string $eventId, InvitationFilters $filters, int $perPage): LengthAwarePaginator;

    /** @return array<string, int> */
    public function summary(string $eventId): array;
}
