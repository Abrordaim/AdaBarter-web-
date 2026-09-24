<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = $this->image_url;
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = url(Storage::url($imageUrl));
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $imageUrl,
            'redirect_url' => $this->redirect_url,
            'advertiser_name' => $this->advertiser_name,
            'position' => $this->position,
            'started_at' => $this->started_at?->toISOString(),
            'expired_at' => $this->expired_at?->toISOString(),
        ];
    }
}
