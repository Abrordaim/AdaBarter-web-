<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'sender_id' => $this->sender_id,
            'message' => $this->message,
            'type' => $this->type,
            'read_at' => $this->read_at?->toISOString(),
            'sender' => new UserResource($this->whenLoaded('sender')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
