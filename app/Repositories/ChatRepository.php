<?php

namespace App\Repositories;

use App\Models\Chat;
use App\Repositories\Contracts\ChatRepositoryInterface;

class ChatRepository extends BaseRepository implements ChatRepositoryInterface
{
    public function __construct(Chat $model)
    {
        parent::__construct($model);
    }

    public function getByOfferId(int $offerId)
    {
        return $this->model->with('sender:id,name,avatar')
            ->where('offer_id', $offerId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function markAsRead(int $offerId, int $currentUserId): int
    {
        return $this->model->where('offer_id', $offerId)
            ->where('sender_id', '!=', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
