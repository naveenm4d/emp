<?php

namespace App\Domains\Event\Services;

use App\Core\Services\BaseService;
use App\Domains\Event\Contracts\EventMediaServiceInterface;
use App\Domains\Event\Events\EventDesignChanged;
use App\Domains\Event\Exceptions\EventNotEditableException;
use App\Domains\Event\Exceptions\UnknownMediaSlotException;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Client uploads for template media slots. Only links to these files end up
 * in the generated invitation; the files stay on the media disk.
 */
class EventMediaService extends BaseService implements EventMediaServiceInterface
{
    public function upload(Event $event, string $slotKey, UploadedFile $file): EventMedia
    {
        $this->ensureEditable($event);

        $slot = $event->templateVersion->slot($slotKey) ?? throw new UnknownMediaSlotException;

        $disk = (string) config('emp.media_disk');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $path = $file->storeAs("events/{$event->id}/media", Str::uuid().'.'.strtolower($extension), ['disk' => $disk]);

        /** @var EventMedia|null $previous */
        $previous = $event->media()->where('slot_key', $slotKey)->first();

        $media = $this->transaction(function () use ($event, $slot, $file, $disk, $path) {
            /** @var EventMedia */
            $media = $event->media()->updateOrCreate(['slot_key' => $slot->key], [
                'type' => $slot->type,
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
            ]);

            EventDesignChanged::dispatch($event);

            return $media;
        });

        if ($previous && $previous->path !== $path) {
            Storage::disk($previous->disk)->delete($previous->path);
        }

        return $media;
    }

    public function delete(EventMedia $media): void
    {
        $this->ensureEditable($media->event);

        $this->transaction(function () use ($media) {
            $media->delete();

            EventDesignChanged::dispatch($media->event);
        });

        Storage::disk($media->disk)->delete($media->path);
    }

    private function ensureEditable(Event $event): void
    {
        if (! $event->state->isEditable()) {
            throw new EventNotEditableException;
        }
    }
}
