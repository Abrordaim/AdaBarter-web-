<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrls = [];
        if (!empty($this->images) && is_array($this->images)) {
            foreach ($this->images as $img) {
                $imageUrls[] = str_starts_with($img, 'http') ? $img : url(Storage::url($img));
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'condition' => $this->condition,
            'desired_items' => $this->desired_items,
            'estimated_price' => $this->estimated_price ? (float) $this->estimated_price : null,
            'location' => $this->location,
            'city' => $this->city,
            'status' => $this->status,
            'is_boosted' => (bool) $this->is_boosted,
            'images' => $imageUrls,
            'primary_image' => $imageUrls[0] ?? null,
            'user' => new UserResource($this->whenLoaded('user')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
