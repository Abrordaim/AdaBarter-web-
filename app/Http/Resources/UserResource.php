<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'province_id' => $this->province_id,
            'province_name' => $this->province_name,
            'regency_id' => $this->regency_id,
            'regency_name' => $this->regency_name,
            'district_id' => $this->district_id,
            'district_name' => $this->district_name,
            'avatar_url' => $this->avatar ? url(Storage::url($this->avatar)) : null,
            'role' => $this->role,
            'is_vip' => $this->isVip(),
            'free_post_quota' => $this->free_post_quota,
            'bonus_post_quota' => $this->bonus_post_quota,
            'remaining_quota' => $this->remainingPostQuota(),
            'can_post' => $this->canPost(),
            'average_rating' => $this->averageRating(),
            'ratings_count' => $this->ratingsCount(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
