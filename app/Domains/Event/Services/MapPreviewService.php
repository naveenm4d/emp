<?php

namespace App\Domains\Event\Services;

use App\Domains\Event\Contracts\MapPreviewServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Only Google Maps URLs are handled, and only Google hosts are ever requested
 * (short links are followed hop by hop), so a pasted link can't make the
 * server call anything else.
 */
class MapPreviewService implements MapPreviewServiceInterface
{
    private const int MAX_REDIRECTS = 5;

    public function embedUrl(string $url): ?string
    {
        $url = trim($url);

        if (! $this->isGoogleMapsUrl($url)) {
            return null;
        }

        if ($this->isShortLink($url)) {
            $url = Cache::remember('map-embed:'.sha1($url), now()->addDay(), fn () => $this->resolveShortLink($url) ?? '');

            if ($url === '') {
                return null;
            }
        }

        $location = $this->location($url);

        return $location === null ? null : 'https://maps.google.com/maps?q='.rawurlencode($location).'&z=15&output=embed';
    }

    private function isGoogleMapsUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';

        return $host === 'maps.app.goo.gl'
            || ($host === 'goo.gl' && str_starts_with($path, '/maps'))
            || (preg_match('/^(www\.|maps\.)?google\.[a-z.]{2,6}$/', $host) === 1
                && (str_starts_with($path, '/maps') || str_starts_with($host, 'maps.')));
    }

    private function isShortLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'maps.app.goo.gl' || $host === 'goo.gl';
    }

    /** Follows the short link's redirects, one hop at a time, while they stay on Google Maps. */
    private function resolveShortLink(string $url): ?string
    {
        for ($hop = 0; $hop < self::MAX_REDIRECTS; $hop++) {
            try {
                $response = Http::withoutRedirecting()->timeout(5)->get($url);
            } catch (ConnectionException) {
                return null;
            }

            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $this->isShortLink($url) ? null : $url;
            }

            if (! $this->isGoogleMapsUrl($location)) {
                return null;
            }

            if (! $this->isShortLink($location)) {
                return $location;
            }

            $url = $location;
        }

        return null;
    }

    /** "lat,lng" or a place name found in a full Google Maps URL. */
    private function location(string $url): ?string
    {
        $decoded = rawurldecode($url);

        // Pin coordinates (!3d…!4d…) are the place itself; @lat,lng is only the viewport.
        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $decoded, $match)) {
            return "{$match[1]},{$match[2]}";
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        foreach (['q', 'query', 'll', 'destination'] as $key) {
            if (is_string($query[$key] ?? null) && trim($query[$key]) !== '') {
                return trim($query[$key]);
            }
        }

        if (preg_match('#/place/([^/@]+)#', $decoded, $match)) {
            return trim(str_replace('+', ' ', $match[1]));
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $decoded, $match)) {
            return "{$match[1]},{$match[2]}";
        }

        return null;
    }
}
