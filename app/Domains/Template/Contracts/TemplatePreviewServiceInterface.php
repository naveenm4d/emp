<?php

namespace App\Domains\Template\Contracts;

use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;

interface TemplatePreviewServiceInterface
{
    /**
     * The version's invitation filled with sample event details, for previews.
     * $details (event form fields: title, description, event_type, event_date,
     * start_time, end_time, location_name, location_address; already validated)
     * replace the samples they cover.
     *
     * @param  array<string, string>  $details
     * @return array{html: string, fonts: list<string>, scripts: list<string>}
     */
    public function render(TemplateVersion $version, array $details = []): array;

    /**
     * The template's latest version rendered for the picker's preview modal.
     *
     * @param  array<string, string>  $details
     * @return array{id: string, name: string, version: string, html: string, fonts: list<string>, scripts: list<string>}
     */
    public function forTemplate(Template $template, array $details = []): array;
}
