<?php

namespace App\Core\Http\Responses;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders web/invitation/show (RSVP and client preview). Link-preview
 * meta tags (WhatsApp, iMessage…) go into the server-rendered <head>
 * because crawlers do not run JS.
 */
final class InvitationPage
{
    /**
     * @param  array<string, mixed>  $design
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $props
     */
    public static function render(array $design, array $event, array $props): Response
    {
        $title = (string) ($event['title'] ?? 'Invitation');

        return Inertia::render('web/invitation/show', [
            ...$props,
            'event' => $event,
            'design' => $design,
        ])->withViewData([
            'meta' => [
                'title' => $title,
                'description' => Str::limit(strip_tags((string) ($event['description'] ?? "You're invited to {$title}.")), 160),
                'image' => $design['cover_image_url'] ?? null,
            ],
        ]);
    }
}
