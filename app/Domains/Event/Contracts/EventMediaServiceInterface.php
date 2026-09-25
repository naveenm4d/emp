<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use Illuminate\Http\UploadedFile;

interface EventMediaServiceInterface
{
    /** Store a file in one of the template's media slots, replacing what was there. */
    public function upload(Event $event, string $slotKey, UploadedFile $file): EventMedia;

    public function delete(EventMedia $media): void;
}
