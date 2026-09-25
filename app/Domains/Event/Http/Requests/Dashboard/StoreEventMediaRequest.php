<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Upload into one media slot. The allowed file types and size come from the
 * slot's type in the event's template (image, video or audio).
 */
class StoreEventMediaRequest extends FormRequest
{
    private function event(): Event
    {
        /** @var Event */
        return $this->route('event');
    }

    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->event()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $slots = $this->event()->templateVersion->slots();
        $slot = collect($slots)->firstWhere('key', $this->input('slot_key'));

        return [
            'slot_key' => ['required', 'string', Rule::in(array_map(fn ($slot) => $slot->key, $slots))],
            'file' => $slot ? $slot->uploadRules() : ['required', 'file'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slot_key.in' => 'This template has no such media placeholder.',
            'file.mimetypes' => 'This file type is not supported here.',
        ];
    }

    public function slotKey(): string
    {
        return $this->string('slot_key')->toString();
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }
}
