<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'quota_amount' => $this->quota_amount,
            'is_claimable' => $this->isClaimable(),
            'expired_at' => $this->expired_at?->toISOString(),
        ];
    }
}
