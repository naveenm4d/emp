<?php

namespace App\Domains\Template\Support;

use App\Domains\Template\Exceptions\InvalidTemplateException;
use App\Domains\Template\Support\Nodes\AssetNode;
use App\Domains\Template\Support\Nodes\IfNode;
use App\Domains\Template\Support\Nodes\Node;
use App\Domains\Template\Support\Nodes\TextNode;
use App\Domains\Template\Support\Nodes\VariableNode;

/**
 * The placeholder language used by invitation templates. It is deliberately
 * tiny and never executes code:
 *
 *   {{ name }}                    value, HTML-escaped
 *   {{#if name}} … {{/if}}        block kept only when name has a value
 *   {{ asset:images/bg.png }}     URL of a file shipped in the template's assets/ folder
 *
 * Names:
 *   img_1 … img_N, video_1 … video_N, bg_music   media slots the client fills
 *   event.*                                      event details (see EVENT_FIELDS)
 *   guest.name                                   filled per guest when viewed
 *   rsvp                                         mount point for the RSVP buttons
 */
final class PlaceholderSyntax
{
    public const RSVP = 'rsvp';

    public const EVENT_FIELDS = [
        'event.title', 'event.description', 'event.type', 'event.date',
        'event.start_time', 'event.end_time', 'event.location_name',
        'event.location_address', 'event.map_url',
    ];

    public const GUEST_FIELDS = ['guest.name'];

    public const ASSET_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'];

    private const MEDIA_PATTERN = '/^(?:(?:img|video)_[1-9][0-9]{0,2}|bg_music)$/';

    private const ASSET_PATTERN = '/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\.[A-Za-z0-9]+$/';

    /** @return list<Node> */
    public static function parse(string $source, PlaceholderContext $context): array
    {
        preg_match_all('/\{\{\s*(.*?)\s*\}\}/s', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        // Open {{#if}} blocks: their names, and the nodes collected at each depth (0 = top level).
        /** @var list<string> $open */
        $open = [];
        /** @var list<list<Node>> $levels */
        $levels = [[]];
        $offset = 0;

        foreach ($matches as $match) {
            [$token, $position] = $match[0];
            $expression = $match[1][0];

            self::appendText($levels, substr($source, $offset, $position - $offset));
            $offset = $position + strlen($token);

            if (preg_match('/^#if\s+(\S+)$/', $expression, $if)) {
                self::assertAllowed($if[1], $context, conditional: true);
                $open[] = $if[1];
                $levels[] = [];

                continue;
            }

            if ($expression === '/if') {
                $name = array_pop($open) ?? throw new InvalidTemplateException('Found {{/if}} without a matching {{#if}}.');
                $children = array_pop($levels);
                $levels[count($levels) - 1][] = new IfNode($name, $children);

                continue;
            }

            if (str_starts_with($expression, 'asset:')) {
                $levels[count($levels) - 1][] = new AssetNode(self::assetPath(substr($expression, 6), $context));

                continue;
            }

            self::assertAllowed($expression, $context, conditional: false);
            $levels[count($levels) - 1][] = new VariableNode($expression);
        }

        self::appendText($levels, substr($source, $offset));

        if ($open !== []) {
            throw new InvalidTemplateException('{{#if '.end($open).'}} is never closed with {{/if}}.');
        }

        return $levels[0];
    }

    public static function isMedia(string $name): bool
    {
        return (bool) preg_match(self::MEDIA_PATTERN, $name);
    }

    /**
     * Escape a value for HTML. Braces are encoded too, so text a client typed
     * (e.g. an event title) can never turn into a placeholder later.
     */
    public static function escape(string $value): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], e($value));
    }

    /** @param list<list<Node>> $levels */
    private static function appendText(array &$levels, string $text): void
    {
        if ($text === '') {
            return;
        }

        if (str_contains($text, '{{')) {
            throw new InvalidTemplateException('Found "{{" that is not a complete placeholder.');
        }

        $levels[count($levels) - 1][] = new TextNode($text);
    }

    private static function assertAllowed(string $name, PlaceholderContext $context, bool $conditional): void
    {
        $kind = match (true) {
            self::isMedia($name) => 'media',
            in_array($name, self::EVENT_FIELDS, true) => 'event',
            in_array($name, self::GUEST_FIELDS, true) => 'guest',
            $name === self::RSVP => 'rsvp',
            default => throw new InvalidTemplateException("Unknown placeholder {{ {$name} }}."),
        };

        $allowed = match ($context) {
            PlaceholderContext::Markup => $conditional ? ['media', 'event', 'guest'] : ['media', 'event', 'guest', 'rsvp'],
            PlaceholderContext::Styles => [],
            PlaceholderContext::Guest => ['guest'],
        };

        if (! in_array($kind, $allowed, true)) {
            $where = $context === PlaceholderContext::Styles ? 'styles.css (only {{ asset:… }} is allowed there)' : 'this position';

            throw new InvalidTemplateException("{{ {$name} }} cannot be used in {$where}.");
        }
    }

    private static function assetPath(string $path, PlaceholderContext $context): string
    {
        if ($context === PlaceholderContext::Guest) {
            throw new InvalidTemplateException('Assets cannot be used here.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! preg_match(self::ASSET_PATTERN, $path) || ! in_array($extension, self::ASSET_EXTENSIONS, true)) {
            throw new InvalidTemplateException("Invalid asset path \"{$path}\". Use letters, numbers, - and _ with one of: ".implode(', ', self::ASSET_EXTENSIONS).'.');
        }

        return $path;
    }
}
