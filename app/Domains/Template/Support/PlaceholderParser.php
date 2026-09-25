<?php

namespace App\Domains\Template\Support;

use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\DTOs\ParsedTemplate;
use App\Domains\Template\Support\Nodes\AssetNode;
use App\Domains\Template\Support\Nodes\IfNode;
use App\Domains\Template\Support\Nodes\Node;
use App\Domains\Template\Support\Nodes\VariableNode;

/**
 * Works out what a template needs from its code. The media placeholders in
 * the code are the slots the client fills: one outside any {{#if}} block is
 * required, one only used inside {{#if}} blocks is optional.
 */
final class PlaceholderParser
{
    public function parse(string $markup, string $styles = ''): ParsedTemplate
    {
        /** @var array<string, bool> $slots key => required */
        $slots = [];
        $fields = [];
        $assets = [];
        $hasRsvp = false;

        $walk = function (array $nodes, bool $conditional) use (&$walk, &$slots, &$fields, &$assets, &$hasRsvp): void {
            /** @var list<Node> $nodes */
            foreach ($nodes as $node) {
                if ($node instanceof IfNode) {
                    $this->record($node->name, false, $slots, $fields);
                    $walk($node->children, true);
                } elseif ($node instanceof VariableNode) {
                    if ($node->name === PlaceholderSyntax::RSVP) {
                        $hasRsvp = true;
                    } else {
                        $this->record($node->name, ! $conditional, $slots, $fields);
                    }
                } elseif ($node instanceof AssetNode) {
                    $assets[$node->path] = true;
                }
            }
        };

        $walk(PlaceholderSyntax::parse($markup, PlaceholderContext::Markup), false);
        $walk(PlaceholderSyntax::parse($styles, PlaceholderContext::Styles), false);

        $mediaSlots = [];

        foreach ($slots as $key => $required) {
            $mediaSlots[] = new MediaSlot($key, MediaSlot::typeOf($key), $required);
        }

        usort($mediaSlots, fn (MediaSlot $a, MediaSlot $b) => strcmp(self::sortKey($a->key), self::sortKey($b->key)));

        return new ParsedTemplate(
            slots: $mediaSlots,
            fields: array_keys($fields),
            assets: array_keys($assets),
            hasRsvp: $hasRsvp,
        );
    }

    /**
     * @param  array<string, bool>  $slots
     * @param  array<string, bool>  $fields
     */
    private function record(string $name, bool $required, array &$slots, array &$fields): void
    {
        if (PlaceholderSyntax::isMedia($name)) {
            $slots[$name] = ($slots[$name] ?? false) || $required;
        } else {
            $fields[$name] = true;
        }
    }

    /** img_1, img_2, …, video_1, …, bg_music */
    private static function sortKey(string $key): string
    {
        if ($key === 'bg_music') {
            return 'c';
        }

        [$type, $number] = explode('_', $key);

        return ($type === 'img' ? 'a' : 'b').str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
