<?php

namespace App\Repositories\Contracts;

interface ChatRepositoryInterface extends BaseRepositoryInterface
{
    public function getByOfferId(int $offerId);
    public function markAsRead(int $offerId, int $currentUserId): int;
}
