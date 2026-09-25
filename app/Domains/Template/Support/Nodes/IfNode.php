<?php

namespace App\Domains\Template\Support\Nodes;

/** {{#if name}} … {{/if}} */
final readonly class IfNode implements Node
{
    /** @param list<Node> $children */
    public function __construct(
        public string $name,
        public array $children,
    ) {}
}
