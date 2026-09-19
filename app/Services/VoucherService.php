<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VoucherRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(
        protected VoucherRepositoryInterface $voucherRepository,
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * Claim a quota voucher.
     *
     * @param User $user
     * @param string $code
     * @return array
     * @throws ValidationException
     */
    public function claimVoucher(User $user, string $code): array
    {
        /** @var Voucher|null $voucher */
        $voucher = $this->voucherRepository->findByCode($code);

        if (!$voucher) {
            throw ValidationException::withMessages([
                'code' => ['Kode voucher tidak ditemukan atau tidak valid.'],
            ]);
        }

        if (!$voucher->is_active) {
            throw ValidationException::withMessages([
                'code' => ['Voucher saat ini sedang tidak aktif.'],
            ]);
        }

        if ($voucher->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Voucher telah melewati batas masa berlaku.'],
            ]);
        }

        if ($voucher->claimed_count >= $voucher->max_claims) {
            throw ValidationException::withMessages([
                'code' => ['Kuota klaim untuk voucher ini sudah habis.'],
            ]);
        }

        if ($this->voucherRepository->hasUserClaimed($voucher->id, $user->id)) {
            throw ValidationException::withMessages([
                'code' => ['Anda sudah pernah mengklaim voucher ini sebelumnya.'],
            ]);
        }

        return DB::transaction(function () use ($user, $voucher) {
            $this->voucherRepository->recordClaim($voucher->id, $user->id);
            $this->userRepository->addBonusQuota($user->id, $voucher->quota_amount);

            $user->refresh();

            return [
                'voucher' => [
                    'code' => $voucher->code,
                    'description' => $voucher->description,
                    'quota_amount' => $voucher->quota_amount,
                ],
                'new_bonus_quota' => $user->bonus_post_quota,
                'remaining_quota' => $user->remainingPostQuota(),
            ];
        });
    }
}
