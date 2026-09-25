<?php

namespace App\Domains\Template\Support;

/**
 * Where a piece of template code is used. Each context allows a different
 * set of placeholders.
 */
enum PlaceholderContext
{
    /** template.html: media, event and guest fields, the RSVP mount, assets. */
    case Markup;

    /** styles.css: template assets only. */
    case Styles;

    /** An already rendered invitation: only the per-guest fields remain. */
    case Guest;
}
