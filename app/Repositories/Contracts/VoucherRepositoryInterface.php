<?php

namespace App\Repositories\Contracts;

use App\Models\VoucherClaim;
use Illuminate\Database\Eloquent\Collection;

interface VoucherRepositoryInterface extends BaseRepositoryInterface
{
    public function findByCode(string $code);
    public function hasUserClaimed(int $voucherId, int $userId): bool;
    public function recordClaim(int $voucherId, int $userId): VoucherClaim;
    public function getAvailablePublicVouchers(): Collection;
    public function getUserVouchers(int $userId, ?string $status = null): Collection;
}
