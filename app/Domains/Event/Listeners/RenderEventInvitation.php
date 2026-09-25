<?php

namespace App\Domains\Event\Listeners;

use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Events\EventDesignChanged;
use Throwable;

/**
 * Regenerates the event's invitation HTML so the preview and guests see
 * the change immediately. Runs synchronously; a failure is reported but
 * does not undo the client's save (the invitation renders on demand later).
 */
class RenderEventInvitation
{
    public function __construct(
        private readonly EventDesignServiceInterface $designs,
    ) {}

    public function handle(EventDesignChanged $event): void
    {
        try {
            $this->designs->render($event->event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
