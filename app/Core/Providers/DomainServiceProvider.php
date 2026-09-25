<?php

namespace App\Core\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider every domain registers in bootstrap/providers.php.
 *
 * Subclasses declare:
 *  - $bindings / $singletons  interface => implementation (native Laravel)
 *  - $policies                model => policy
 *  - $listen                  domain event => listeners
 */
abstract class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected array $policies = [];

    /** @var array<class-string, list<class-string>> */
    protected array $listen = [];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        $this->bootDomain();
    }

    /** Hook for domain specific boot logic. */
    protected function bootDomain(): void
    {
        //
    }
}
