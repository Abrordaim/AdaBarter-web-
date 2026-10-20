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
            'payment_method' => $this->payment_method ?? 'cod',
            'price_difference' => (int) ($this->price_difference ?? 0),
            'payer_id' => $this->payer_id,
            'receiver_id' => $this->receiver_id,
            'escrow_status' => $this->escrow_status?->value ?? (is_string($this->escrow_status) ? $this->escrow_status : null),
            'payer_confirmed_at' => $this->payer_confirmed_at?->toISOString(),
            'receiver_confirmed_at' => $this->receiver_confirmed_at?->toISOString(),
            'confirm_deadline_at' => $this->confirm_deadline_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'handover_code' => ($user && in_array($user->id, [$this->payer_id, $this->receiver_id, $this->offerer_user_id, $this->target_user_id])) ? $this->handover_code : null,
            'dispute' => ($latestDispute = $this->relationLoaded('disputes') ? $this->disputes->first() : $this->disputes()->latest('id')->first()) ? [
                'id' => $latestDispute->id,
                'reported_by' => $latestDispute->reported_by,
                'reason' => $latestDispute->reason,
                'description' => $latestDispute->description,
                'status' => $latestDispute->status,
                'resolution' => $latestDispute->resolution,
                'refund_amount' => $latestDispute->refund_amount,
                'admin_note' => $latestDispute->admin_note,
                'created_at' => $latestDispute->created_at?->toISOString(),
                'resolved_at' => $latestDispute->resolved_at?->toISOString(),
            ] : null,
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
            'offerer_items' => ItemResource::collection(
                $this->relationLoaded('offererItems') && $this->offererItems->isNotEmpty()
                    ? $this->offererItems
                    : ($this->relationLoaded('offererItem') && $this->offererItem ? collect([$this->offererItem]) : collect())
            ),
            'total_offerer_estimated_price' => $this->totalOffererEstimatedPrice(),
            'target_item' => new ItemResource($this->whenLoaded('targetItem')),
            'latest_message' => new ChatResource($this->whenLoaded('chats', fn () => $this->chats->first())),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
