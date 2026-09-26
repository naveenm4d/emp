<?php

namespace App\Domains\Staff\Http\Resources;

use App\Domains\Staff\Models\StaffActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin StaffActivity
 */
class StaffActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'staff' => $this->staffMember ? ['id' => $this->staffMember->id, 'name' => $this->staffMember->name] : null,
            'client' => $this->whenLoaded('client', fn () => $this->client ? ['id' => $this->client->id, 'name' => $this->client->name] : null),
            'subject_type' => $this->subject_type ? Str::headline(class_basename($this->subject_type)) : null,
            'subject_id' => $this->subject_id,
            'changes' => $this->changes,
            'note' => $this->note,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
