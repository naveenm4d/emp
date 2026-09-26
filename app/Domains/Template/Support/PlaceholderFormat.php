<?php

namespace App\Domains\Template\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * How event details read inside an invitation. Shared by real invitations
 * (EventDesignService) and template previews, so both look the same.
 */
final class PlaceholderFormat
{
    /** "Saturday, 12 October 2026" */
    public static function date(?CarbonInterface $date): ?string
    {
        return $date?->translatedFormat('l, j F Y');
    }

    /** "6:00 PM" from "18:00" or "18:00:00" */
    public static function time(?string $time): ?string
    {
        return $time ? Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? "{$time}:00" : $time)?->format('g:i A') : null;
    }

    /** Escaped text with line breaks kept. */
    public static function multiline(?string $text): ?HtmlString
    {
        return filled($text) ? new HtmlString(nl2br(PlaceholderSyntax::escape((string) $text), false)) : null;
    }

    /** Only http(s) links are placed into href attributes. */
    public static function url(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? $url : null;
    }
}
