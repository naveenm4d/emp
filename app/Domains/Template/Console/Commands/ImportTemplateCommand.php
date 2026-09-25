<?php

namespace App\Domains\Template\Console\Commands;

use App\Core\Exceptions\DomainException;
use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('templates:import {path : Template package folder or .zip} {--skip-unchanged : Succeed quietly when the same version was already imported}')]
#[Description('Validate an invitation template package and publish it to the template disk')]
class ImportTemplateCommand extends Command
{
    public function handle(TemplateImportServiceInterface $importer): int
    {
        try {
            $version = $importer->import((string) $this->argument('path'), (bool) $this->option('skip-unchanged'));
        } catch (DomainException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $version->load('template');
        $slots = collect($version->slots())->map(fn ($slot) => $slot->key.($slot->required ? '*' : ''))->implode(', ');

        $this->components->info("Imported {$version->template->name} ({$version->template->key}) v{$version->version}.");
        $this->components->twoColumnDetail('Stored at', "{$version->disk}:{$version->path}");
        $this->components->twoColumnDetail('Media slots (* = required)', $slots ?: 'none');

        return self::SUCCESS;
    }
}
