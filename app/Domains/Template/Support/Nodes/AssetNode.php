<?php

namespace App\Domains\Template\Support\Nodes;

/** {{ asset:images/ornament.png }} */
final readonly class AssetNode implements Node
{
    public function __construct(public string $path) {}
}
