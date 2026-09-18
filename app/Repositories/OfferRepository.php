<?php

namespace App\Repositories;

use App\Models\Offer;
use App\Repositories\Contracts\OfferRepositoryInterface;

class OfferRepository extends BaseRepository implements OfferRepositoryInterface
{
    public function __construct(Offer $model)
    {
        parent::__construct($model);
    }

    public function getUserOffers(int $userId, ?string $type = null, ?string $status = null)
    {
        $query = $this->model->with([
            'offerer:id,name,city,avatar',
            'targetOwner:id,name,city,avatar',
            'offererItem:id,title,images,condition,estimated_price',
            'targetItem:id,title,images,condition,estimated_price'
        ]);

        if ($type === 'sent') {
            $query->where('offerer_user_id', $userId);
        } elseif ($type === 'received') {
            $query->where('target_user_id', $userId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('offerer_user_id', $userId)
                  ->orWhere('target_user_id', $userId);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function findForUser(int $id, int $userId)
    {
        return $this->model->with([
            'offerer:id,name,city,avatar',
            'targetOwner:id,name,city,avatar',
            'offererItem',
            'targetItem'
        ])->where('id', $id)
          ->where(function ($q) use ($userId) {
              $q->where('offerer_user_id', $userId)
                ->orWhere('target_user_id', $userId);
          })->first();
    }

    public function getMatchedOffersForUser(int $userId)
    {
        return $this->model->with([
            'offerer:id,name,city,avatar',
            'targetOwner:id,name,city,avatar',
            'offererItem:id,title,images',
            'targetItem:id,title,images',
            'chats' => function ($q) {
                $q->latest()->limit(1);
            }
        ])->where(function ($q) use ($userId) {
            $q->where('offerer_user_id', $userId)
              ->orWhere('target_user_id', $userId);
        })->whereIn('status', ['matched', 'completed'])
          ->orderByDesc('matched_at')
          ->get();
    }

    public function existsPendingOffer(int $offererItemId, int $targetItemId): bool
    {
        return $this->model->where('offerer_item_id', $offererItemId)
            ->where('target_item_id', $targetItemId)
            ->where('status', 'pending')
            ->exists();
    }
}
