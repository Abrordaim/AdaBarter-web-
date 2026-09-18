<?php

namespace App\Repositories\Contracts;

interface VoucherRepositoryInterface extends BaseRepositoryInterface
{
    public function findByCode(string $code);
    public function hasUserClaimed(int $voucherId, int $userId): bool;
    public function recordClaim(int $voucherId, int $userId): bool;
}
