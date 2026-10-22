<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Repositories\Contracts\VoucherRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class VoucherService
{
    public function __construct(
        protected VoucherRepositoryInterface $voucherRepository
    ) {}

    /**
     * Ambil daftar voucher publik yang aktif dan dapat diklaim oleh pengguna.
     *
     * @param User $user
     * @return array
     */
    public function getAvailablePublicVouchers(User $user): array
    {
        $vouchers = $this->voucherRepository->getAvailablePublicVouchers();
        $claimedVoucherIds = VoucherClaim::where('user_id', $user->id)
            ->pluck('voucher_id')
            ->toArray();

        return $vouchers->map(function (Voucher $voucher) use ($claimedVoucherIds) {
            return [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'description' => $voucher->description,
                'type' => $voucher->type,
                'discount_amount' => $voucher->discount_amount,
                'percentage' => $voucher->percentage,
                'max_discount_amount' => $voucher->max_discount_amount,
                'min_transaction_amount' => $voucher->min_transaction_amount,
                'distribution' => $voucher->distribution,
                'max_claims' => $voucher->max_claims,
                'claimed_count' => $voucher->claimed_count,
                'remaining_claims' => max(0, $voucher->max_claims - $voucher->claimed_count),
                'formatted_discount' => $voucher->formattedDiscount(),
                'expired_at' => $voucher->expired_at?->toISOString(),
                'is_claimed' => in_array($voucher->id, $claimedVoucherIds, true),
            ];
        })->values()->all();
    }

    /**
     * Ambil daftar voucher yang dimiliki oleh pengguna (aktif atau sudah dipakai).
     *
     * @param User $user
     * @param string|null $status 'active' | 'used' | null
     * @return array
     */
    public function getMyVouchers(User $user, ?string $status = null): array
    {
        $claims = $this->voucherRepository->getUserVouchers($user->id, $status);

        return $claims->map(function (VoucherClaim $claim) {
            $voucher = $claim->voucher;
            return [
                'claim_id' => $claim->id,
                'is_used' => $claim->is_used,
                'used_at' => $claim->used_at?->toISOString(),
                'claimed_at' => $claim->claimed_at->toISOString(),
                'offer_id' => $claim->offer_id,
                'voucher' => [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'description' => $voucher->description,
                    'type' => $voucher->type,
                    'discount_amount' => $voucher->discount_amount,
                    'percentage' => $voucher->percentage,
                    'max_discount_amount' => $voucher->max_discount_amount,
                    'min_transaction_amount' => $voucher->min_transaction_amount,
                    'distribution' => $voucher->distribution,
                    'formatted_discount' => $voucher->formattedDiscount(),
                    'expired_at' => $voucher->expired_at?->toISOString(),
                    'is_expired' => $voucher->isExpired(),
                    'is_usable' => !$claim->is_used && !$voucher->isExpired() && $voucher->is_active,
                ],
            ];
        })->values()->all();
    }

    /**
     * Klaim voucher diskon:
     * - Metode 1: Menggunakan kode promo (bisa private atau public)
     * - Metode 2: Menggunakan voucher_id (hanya untuk voucher public)
     *
     * @param User $user
     * @param string|null $code
     * @param int|null $voucherId
     * @return array
     * @throws ValidationException
     */
    public function claimVoucher(User $user, ?string $code = null, ?int $voucherId = null): array
    {
        /** @var Voucher|null $voucher */
        $voucher = null;

        if (!empty($voucherId)) {
            $voucher = Voucher::find($voucherId);
            if (!$voucher) {
                throw ValidationException::withMessages([
                    'voucher_id' => ['Voucher tidak ditemukan.'],
                ]);
            }
            if ($voucher->distribution !== 'public') {
                throw ValidationException::withMessages([
                    'voucher_id' => ['Voucher ini bersifat privat dan hanya dapat diklaim menggunakan kode promo khusus.'],
                ]);
            }
        } elseif (!empty($code)) {
            $voucher = $this->voucherRepository->findByCode(trim($code));
            if (!$voucher) {
                throw ValidationException::withMessages([
                    'code' => ['Kode voucher tidak ditemukan atau salah ketik.'],
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'code' => ['Harap masukkan kode voucher atau pilih voucher yang tersedia.'],
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
            $claim = $this->voucherRepository->recordClaim($voucher->id, $user->id);

            if ($voucher->quota_amount && $voucher->quota_amount > 0) {
                $user->increment('bonus_post_quota', $voucher->quota_amount);
                $user->refresh();
            }

            return [
                'claim_id' => $claim->id,
                'claimed_at' => $claim->claimed_at->toISOString(),
                'new_bonus_quota' => $user->bonus_post_quota,
                'remaining_quota' => $user->remainingPostQuota(),
                'voucher' => [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'description' => $voucher->description,
                    'type' => $voucher->type,
                    'discount_amount' => $voucher->discount_amount,
                    'percentage' => $voucher->percentage,
                    'max_discount_amount' => $voucher->max_discount_amount,
                    'min_transaction_amount' => $voucher->min_transaction_amount,
                    'distribution' => $voucher->distribution,
                    'formatted_discount' => $voucher->formattedDiscount(),
                    'expired_at' => $voucher->expired_at?->toISOString(),
                ],
            ];
        });
    }

    /**
     * Memvalidasi apakah klaim voucher milik pengguna valid untuk transaksi selisih barter tertentu.
     *
     * @param User $user
     * @param int $voucherClaimId
     * @param Offer $offer
     * @return array{claim: VoucherClaim, voucher: Voucher, discount_amount: int, gross_amount: int}
     * @throws InvalidArgumentException
     */
    public function validateVoucherForOffer(User $user, int $voucherClaimId, Offer $offer): array
    {
        /** @var VoucherClaim|null $claim */
        $claim = VoucherClaim::with('voucher')
            ->where('id', $voucherClaimId)
            ->where('user_id', $user->id)
            ->first();

        if (!$claim) {
            throw new InvalidArgumentException('Voucher tidak ditemukan dalam daftar voucher milik Anda.');
        }

        if ($claim->is_used) {
            throw new InvalidArgumentException('Voucher ini sudah pernah digunakan untuk transaksi lain.');
        }

        $voucher = $claim->voucher;
        if (!$voucher || !$voucher->is_active) {
            throw new InvalidArgumentException('Voucher saat ini sedang tidak aktif.');
        }

        if ($voucher->isExpired()) {
            throw new InvalidArgumentException('Voucher telah kedaluwarsa.');
        }

        $priceDifference = (int) $offer->price_difference;
        if ($priceDifference < $voucher->min_transaction_amount) {
            throw new InvalidArgumentException(
                'Minimal selisih barter untuk menggunakan voucher ini adalah Rp ' .
                number_format($voucher->min_transaction_amount, 0, ',', '.')
            );
        }

        $discountAmount = $voucher->calculateDiscount($priceDifference);
        
        // Midtrans mewajibkan nominal transaksi pembayaran minimal Rp 1.000
        $grossAmount = max(1000, $priceDifference - $discountAmount);

        return [
            'claim' => $claim,
            'voucher' => $voucher,
            'discount_amount' => $discountAmount,
            'gross_amount' => $grossAmount,
        ];
    }
}
