<?php

namespace Database\Seeders;

use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Imports the starter template packages in database/seeders/templates
 * ({key}/{version}/) onto the template disk. Safe to run repeatedly.
 * In production, templates are published with `php artisan templates:import`.
 */
class TemplateSeeder extends Seeder
{
    public function run(TemplateImportServiceInterface $importer): void
    {
        foreach (File::directories(__DIR__.'/templates') as $template) {
            $versions = File::directories($template);
            usort($versions, fn (string $a, string $b) => version_compare(basename($a), basename($b)));

            foreach ($versions as $version) {
                $importer->import($version, skipUnchanged: true);
            }
        }
    }
}
