<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventMediaServiceInterface;
use App\Domains\Event\Http\Requests\Dashboard\StoreEventMediaRequest;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use Illuminate\Http\RedirectResponse;

class EventMediaController extends InertiaController
{
    public function __construct(
        private readonly EventMediaServiceInterface $media,
    ) {}

    public function store(StoreEventMediaRequest $request, Event $event): RedirectResponse
    {
        $this->media->upload($event, $request->slotKey(), $request->upload());

        return $this->backWithSuccess('Media uploaded.');
    }

    public function destroy(Event $event, EventMedia $media): RedirectResponse
    {
        $this->authorize('update', $event);

        $this->media->delete($media);

        return $this->backWithSuccess('Media removed.');
    }
}
