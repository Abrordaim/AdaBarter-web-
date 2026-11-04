<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $otherUser = $currentUserId ? $this->getOtherParticipant($currentUserId) : null;

        // Count unread messages for current user
        $unreadCount = 0;
        if ($currentUserId) {
            $unreadCount = $this->messages()
                ->where('sender_id', '!=', $currentUserId)
                ->whereNull('read_at')
                ->count();
        }

        return [
            'id' => $this->id,
            'initiator_id' => $this->initiator_id,
            'receiver_id' => $this->receiver_id,
            'item_id' => $this->item_id,
            'offer_id' => $this->offer_id,
            'offer' => $this->whenLoaded('offer', function () {
                return new OfferResource($this->offer);
            }),
            'is_matched' => $this->relationLoaded('offer') && in_array($this->offer?->status, ['matched', 'completed']),
            'last_message_at' => $this->last_message_at?->toISOString() ?? $this->created_at?->toISOString(),
            'initiator' => new UserResource($this->whenLoaded('initiator')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
            'other_user' => $otherUser ? new UserResource($otherUser) : null,
            'item' => $this->whenLoaded('item', function () {
                return [
                    'id' => $this->item->id,
                    'title' => $this->item->title,
                    'condition' => $this->item->condition,
                    'estimated_price' => $this->item->estimated_price,
                    'city' => $this->item->regency_name ?? $this->item->city,
                    'primary_image' => $this->item->images[0] ?? null,
                    'images' => $this->item->images ?? [],
                    'user_id' => $this->item->user_id,
                ];
            }),
            'latest_message' => new DirectMessageResource($this->whenLoaded('latestMessage')),
            'unread_count' => $unreadCount,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
