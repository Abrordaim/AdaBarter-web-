<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Repositories\Contracts\VoucherRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VoucherRepository extends BaseRepository implements VoucherRepositoryInterface
{
    public function __construct(Voucher $model)
    {
        parent::__construct($model);
    }

    public function findByCode(string $code)
    {
        return $this->model->where('code', strtoupper($code))->first();
    }

    public function hasUserClaimed(int $voucherId, int $userId): bool
    {
        return VoucherClaim::where('voucher_id', $voucherId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function recordClaim(int $voucherId, int $userId): VoucherClaim
    {
        return DB::transaction(function () use ($voucherId, $userId) {
            $claim = VoucherClaim::create([
                'voucher_id' => $voucherId,
                'user_id' => $userId,
                'claimed_at' => now(),
                'is_used' => false,
            ]);

            $this->model->where('id', $voucherId)->increment('claimed_count');

            return $claim->load('voucher');
        });
    }

    public function getAvailablePublicVouchers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->newQuery()
            ->public()
            ->available()
            ->latest()
            ->get();
    }

    public function getUserVouchers(int $userId, ?string $status = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = VoucherClaim::with('voucher')
            ->where('user_id', $userId);

        if ($status === 'active') {
            $query->available();
        } elseif ($status === 'used') {
            $query->used();
        }

        return $query->latest('claimed_at')->get();
    }
}
