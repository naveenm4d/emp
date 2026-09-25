<?php

namespace App\Domains\Template\Contracts;

use App\Domains\Template\Models\TemplateVersion;

interface TemplateImportServiceInterface
{
    /**
     * Import a template package (a folder or .zip containing template.json,
     * template.html, styles.css, optional thumbnail and assets/).
     *
     * @param  bool  $skipUnchanged  return the existing version instead of failing when the same code was already imported
     */
    public function import(string $source, bool $skipUnchanged = false): TemplateVersion;
}
