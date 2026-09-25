<?php

namespace App\Domains\Template\Support\Nodes;

/** {{ name }} */
final readonly class VariableNode implements Node
{
    public function __construct(public string $name) {}
}
