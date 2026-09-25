<?php

namespace App\Domains\Event\Http\Controllers\Site;

use App\Core\Http\Controllers\Controller;
use App\Core\Http\Responses\InvitationPage;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Http\Resources\PublicEventResource;
use App\Domains\Event\Models\Event;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The client's preview of the generated invitation, opened from the
 * dashboard through a signed link (Event::previewUrl). Works for draft and
 * incomplete events; RSVP buttons are shown but disabled.
 */
class PreviewController extends Controller
{
    public function __construct(
        private readonly EventDesignServiceInterface $designs,
    ) {}

    public function show(Request $request, Event $event): Response
    {
        $response = InvitationPage::render(
            $this->designs->forGuest($event, 'Guest Name'),
            PublicEventResource::make($event)->resolve($request),
            ['mode' => 'preview', 'invitation' => null, 'guest' => null, 'actions' => null],
        )->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
