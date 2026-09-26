<?php

namespace App\Domains\Template\Contracts;

use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Http\UploadedFile;

interface TemplateImportServiceInterface
{
    /**
     * Import a template package (a folder or .zip containing template.json,
     * index.html, and optional css/, js/, assets/ and thumbnail).
     *
     * @param  bool  $skipUnchanged  return the existing version instead of failing when the same code was already imported
     */
    public function import(string $source, bool $skipUnchanged = false): TemplateVersion;

    /** Import a .zip package uploaded through the admin console. */
    public function importUploaded(UploadedFile $file): TemplateVersion;
}
