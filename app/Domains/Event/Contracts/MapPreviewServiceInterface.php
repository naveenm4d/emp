<?php

namespace App\Domains\Event\Contracts;

/**
 * Turns the Google Maps link a client pastes into an embeddable map, so the
 * event form can show where the link points.
 */
interface MapPreviewServiceInterface
{
    /** The Google Maps embed URL for a maps link, or null when it is not a Google Maps link with a location. */
    public function embedUrl(string $url): ?string;
}
