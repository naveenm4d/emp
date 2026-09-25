<?php

namespace App\Domains\Template\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Template\Console\Commands\ImportTemplateCommand;
use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\Repositories\TemplateRepository;
use App\Domains\Template\Services\TemplateImportService;
use App\Domains\Template\Services\TemplateQueryService;

class TemplateServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        TemplateRepositoryInterface::class => TemplateRepository::class,
        TemplateQueryServiceInterface::class => TemplateQueryService::class,
        TemplateImportServiceInterface::class => TemplateImportService::class,
    ];

    protected function bootDomain(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportTemplateCommand::class]);
        }
    }
}
