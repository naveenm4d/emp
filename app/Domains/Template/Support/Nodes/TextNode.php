<?php

namespace App\Domains\Template\Support\Nodes;

final readonly class TextNode implements Node
{
    public function __construct(public string $text) {}
}
