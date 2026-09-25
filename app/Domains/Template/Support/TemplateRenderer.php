<?php

namespace App\Domains\Template\Support;

use App\Domains\Template\Support\Nodes\AssetNode;
use App\Domains\Template\Support\Nodes\IfNode;
use App\Domains\Template\Support\Nodes\Node;
use App\Domains\Template\Support\Nodes\TextNode;
use App\Domains\Template\Support\Nodes\VariableNode;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fills placeholders with values. Plain strings are HTML-escaped; values
 * that are already safe HTML are passed as Htmlable.
 *
 * Names listed in $defer are written back out as placeholders, untouched,
 * so they can be filled in a later pass (used for per-guest fields).
 */
final class TemplateRenderer
{
    public const RSVP_MOUNT = '<div data-emp-rsvp></div>';

    /**
     * @param  list<Node>  $nodes
     * @param  array<string, string|Htmlable|null>  $values
     * @param  (Closure(string): string)|null  $assetUrl
     * @param  list<string>  $defer
     */
    public function render(array $nodes, array $values = [], ?Closure $assetUrl = null, array $defer = []): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= match (true) {
                $node instanceof TextNode => $node->text,
                $node instanceof AssetNode => $assetUrl ? PlaceholderSyntax::escape($assetUrl($node->path)) : '',
                $node instanceof VariableNode => $this->variable($node->name, $values, $defer),
                $node instanceof IfNode => $this->conditional($node, $values, $assetUrl, $defer),
                default => '',
            };
        }

        return $html;
    }

    /**
     * @param  array<string, string|Htmlable|null>  $values
     * @param  list<string>  $defer
     */
    private function variable(string $name, array $values, array $defer): string
    {
        if (in_array($name, $defer, true)) {
            return "{{ {$name} }}";
        }

        if ($name === PlaceholderSyntax::RSVP) {
            return self::RSVP_MOUNT;
        }

        $value = $values[$name] ?? null;

        return $value instanceof Htmlable ? $value->toHtml() : PlaceholderSyntax::escape((string) $value);
    }

    /**
     * @param  array<string, string|Htmlable|null>  $values
     * @param  (Closure(string): string)|null  $assetUrl
     * @param  list<string>  $defer
     */
    private function conditional(IfNode $node, array $values, ?Closure $assetUrl, array $defer): string
    {
        $inner = $this->render($node->children, $values, $assetUrl, $defer);

        if (in_array($node->name, $defer, true)) {
            return "{{#if {$node->name}}}{$inner}{{/if}}";
        }

        $value = $values[$node->name] ?? null;
        $present = $value instanceof Htmlable ? $value->toHtml() !== '' : filled($value);

        return $present ? $inner : '';
    }
}
