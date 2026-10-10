<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $myRating = null;
        if ($user && $this->status === 'completed') {
            if ($this->relationLoaded('ratings')) {
                $myRating = $this->ratings->firstWhere('rater_id', $user->id);
            } else {
                $myRating = $this->ratings()->where('rater_id', $user->id)->first();
            }
        }

        return [
            'id' => $this->id,
            'cash_supplement' => $this->cash_supplement ? (float) $this->cash_supplement : null,
            'cash_supplement_by' => $this->cash_supplement_by,
            'status' => $this->status,
            'offerer_approved' => (bool) $this->offerer_approved,
            'target_approved' => (bool) $this->target_approved,
            'matched_at' => $this->matched_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'has_rated' => $myRating !== null,
            'my_rating' => $myRating ? [
                'id' => $myRating->id,
                'rating' => (int) $myRating->rating,
                'comment' => $myRating->comment,
            ] : null,
            'offerer' => new UserResource($this->whenLoaded('offerer')),
            'target_owner' => new UserResource($this->whenLoaded('targetOwner')),
            'offerer_item' => new ItemResource($this->whenLoaded('offererItem')),
            'target_item' => new ItemResource($this->whenLoaded('targetItem')),
            'latest_message' => new ChatResource($this->whenLoaded('chats', fn () => $this->chats->first())),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
