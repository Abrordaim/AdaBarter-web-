<?php

namespace App\Repositories\Contracts;

interface OfferRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserOffers(int $userId, ?string $type = null, ?string $status = null);

    public function findForUser(int $id, int $userId);

    public function getMatchedOffersForUser(int $userId);

    public function existsPendingOffer(int $offererItemId, int $targetItemId): bool;
}
