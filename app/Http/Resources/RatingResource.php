<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'rater' => $this->whenLoaded('rater', function () {
                return [
                    'id' => $this->rater->id,
                    'name' => $this->rater->name,
                    'avatar_url' => $this->rater->avatar ? url(Storage::url($this->rater->avatar)) : null,
                    'city' => $this->rater->city,
                    'is_vip' => $this->rater->isVip(),
                ];
            }),
            'rated_user' => $this->whenLoaded('ratedUser', function () {
                return [
                    'id' => $this->ratedUser->id,
                    'name' => $this->ratedUser->name,
                    'avatar_url' => $this->ratedUser->avatar ? url(Storage::url($this->ratedUser->avatar)) : null,
                    'city' => $this->ratedUser->city,
                ];
            }),
        ];
    }
}
