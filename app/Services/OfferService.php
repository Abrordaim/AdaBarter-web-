<?php

namespace App\Services;

use App\Enums\EscrowStatus;
use App\Models\Chat;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use App\Models\VoucherClaim;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\OfferRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferService
{
    public function __construct(
        protected OfferRepositoryInterface $offerRepository,
        protected ItemRepositoryInterface $itemRepository
    ) {}

    public function getUserOffers(User $user, ?string $type = null, ?string $status = null)
    {
        return $this->offerRepository->getUserOffers($user->id, $type, $status);
    }

    public function getOfferDetail(User $user, int $id)
    {
        $offer = $this->offerRepository->findForUser($id, $user->id);

        if (!$offer) {
            throw new \Exception('Penawaran barter tidak ditemukan.');
        }

        return $offer;
    }

    /**
     * Submit a barter or tukar tambah offer (supports multiple offered items).
     *
     * @param User $user
     * @param array $data
     * @return Offer
     * @throws ValidationException
     */
    public function createOffer(User $user, array $data): Offer
    {
        if ($user->hasRestriction('no_offer')) {
            throw ValidationException::withMessages([
                'restriction' => ['Akun Anda sedang dibatasi dari membuat penawaran barter baru karena akumulasi poin penalti.'],
            ]);
        }

        // Support both offerer_item_ids (array) and offerer_item_id (single)
        $offererItemIds = [];
        if (!empty($data['offerer_item_ids']) && is_array($data['offerer_item_ids'])) {
            $offererItemIds = array_map('intval', $data['offerer_item_ids']);
        } elseif (!empty($data['offerer_item_id'])) {
            $offererItemIds = [(int) $data['offerer_item_id']];
        }

        if (empty($offererItemIds)) {
            throw ValidationException::withMessages([
                'offerer_item_ids' => ['Pilih minimal satu barang untuk ditukarkan.'],
            ]);
        }

        $targetItemId = (int) $data['target_item_id'];

        /** @var Item|null $targetItem */
        $targetItem = $this->itemRepository->find($targetItemId);

        if (!$targetItem || $targetItem->status !== 'active') {
            throw ValidationException::withMessages([
                'target_item_id' => ['Barang target tidak ditemukan atau sudah tidak aktif.'],
            ]);
        }

        if ($targetItem->user_id === $user->id) {
            throw ValidationException::withMessages([
                'target_item_id' => ['Anda tidak dapat mengajukan barter terhadap barang Anda sendiri.'],
            ]);
        }

        // Validate all offerer items
        $offererItems = [];
        $totalOffererPrice = 0;
        foreach ($offererItemIds as $itemId) {
            $item = $this->itemRepository->find($itemId);

            if (!$item || $item->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'offerer_item_ids' => ["Barang penawar (#{$itemId}) tidak valid atau bukan milik Anda."],
                ]);
            }

            if ($item->status !== 'active') {
                throw ValidationException::withMessages([
                    'offerer_item_ids' => ["Barang '{$item->title}' tidak sedang aktif."],
                ]);
            }

            // Check if there's already a pending offer for any of these items against targetItem
            if ($this->offerRepository->existsPendingOffer($itemId, $targetItemId)) {
                throw ValidationException::withMessages([
                    'target_item_id' => ["Anda sudah memiliki penawaran barter yang sedang diproses untuk barang '{$item->title}' terhadap barang target ini."],
                ]);
            }

            $offererItems[] = $item;
            $totalOffererPrice += (float) ($item->estimated_price ?? 0);
        }

        // Auto-calculate cash supplement if not manually provided
        $cashSupplement = $data['cash_supplement'] ?? null;
        $cashSupplementBy = $data['cash_supplement_by'] ?? null;

        $targetPrice = (float) ($targetItem->estimated_price ?? 0);
        if ($cashSupplement === null && $totalOffererPrice > 0 && $targetPrice > 0) {
            $diff = $targetPrice - $totalOffererPrice;
            if ($diff > 0) {
                $cashSupplement = $diff;
                $cashSupplementBy = 'offerer';
            } elseif ($diff < 0) {
                $cashSupplement = abs($diff);
                $cashSupplementBy = 'target_owner';
            }
        }

        $paymentMethod = $data['payment_method'] ?? 'cod';

        return DB::transaction(function () use ($user, $offererItems, $offererItemIds, $targetItem, $targetItemId, $cashSupplement, $cashSupplementBy, $paymentMethod) {
            $primaryOffererItemId = $offererItemIds[0];

            $offerData = [
                'offerer_user_id'    => $user->id,
                'offerer_item_id'    => $primaryOffererItemId,
                'target_user_id'     => $targetItem->user_id,
                'target_item_id'     => $targetItemId,
                'payment_method'     => $paymentMethod,
                'cash_supplement'    => $cashSupplement,
                'cash_supplement_by' => $cashSupplementBy,
                'status'             => 'pending',
                'offerer_approved'   => true,
                'target_approved'    => false,
            ];

            /** @var Offer $offer */
            $offer = $this->offerRepository->create($offerData);

            // Attach all offerer items into offer_items pivot
            $attachData = [];
            foreach ($offererItemIds as $itemId) {
                $attachData[$itemId] = ['role' => 'offerer'];
            }
            $offer->offererItems()->sync($attachData);

            // Hitung selisih harga dan tentukan pihak payer / receiver
            $parties = $offer->calculatePriceDifferenceAndParties();
            $offer->update([
                'price_difference'  => $parties['price_difference'],
                'payer_id'          => $parties['payer_id'],
                'receiver_id'       => $parties['receiver_id'],
                'handover_code'     => str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
                'handover_attempts' => 0,
            ]);

            return $offer->load(['offererItems', 'offererItem', 'targetItem', 'offerer', 'targetOwner']);
        });
    }

    /**
     * Target owner accepts the offer (mutually agreed / matched).
     *
     * @param User $user
     * @param int $id
     * @return Offer
     * @throws AuthorizationException
     */
    public function acceptOffer(User $user, int $id): Offer
    {
        /** @var Offer|null $offer */
        $offer = $this->offerRepository->find($id);

        if (!$offer) {
            throw new \Exception('Penawaran tidak ditemukan.');
        }

        if ($offer->target_user_id !== $user->id) {
            throw new AuthorizationException('Hanya pemilik barang target yang dapat menerima penawaran ini.');
        }

        if ($offer->status !== 'pending') {
            throw new \Exception('Penawaran ini sudah tidak berstatus pending.');
        }

        return DB::transaction(function () use ($offer) {
            $updateData = [
                'target_approved' => true,
                'status' => 'matched',
                'matched_at' => now(),
            ];

            if ($offer->isEscrow() && $offer->price_difference > 0) {
                $updateData['escrow_status'] = EscrowStatus::WAITING_PAYMENT;
            }

            $updated = $this->offerRepository->update($offer->id, $updateData);

            // Create initial system message to start the negotiation room
            Chat::create([
                'offer_id' => $offer->id,
                'sender_id' => $offer->target_user_id,
                'message' => 'Penawaran barter telah disetujui bersama (Matched)! Silakan gunakan ruang obrolan ini untuk mendiskusikan detail kondisi barang dan titik temu pertemuan langsung (COD).',
                'type' => 'system',
            ]);

            return $updated;
        });
    }

    /**
     * Target owner rejects the offer.
     *
     * @param User $user
     * @param int $id
     * @param string|null $reason
     * @return Offer
     * @throws AuthorizationException
     */
    public function rejectOffer(User $user, int $id, ?string $reason = null): Offer
    {
        /** @var Offer|null $offer */
        $offer = $this->offerRepository->find($id);

        if (!$offer) {
            throw new \Exception('Penawaran tidak ditemukan.');
        }

        if ($offer->target_user_id !== $user->id) {
            throw new AuthorizationException('Hanya pemilik barang target yang dapat menolak penawaran ini.');
        }

        if ($offer->status !== 'pending') {
            throw new \Exception('Penawaran ini sudah tidak berstatus pending.');
        }

        return $this->offerRepository->update($offer->id, [
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark the barter transaction as completed after COD.
     *
     * @param User $user
     * @param int $id
     * @return Offer
     * @throws AuthorizationException
     */
    public function completeOffer(User $user, int $id): Offer
    {
        /** @var Offer|null $offer */
        $offer = $this->offerRepository->find($id);

        if (!$offer) {
            throw new \Exception('Penawaran tidak ditemukan.');
        }

        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id) {
            throw new AuthorizationException('Anda tidak berwenang menyelesaikan penawaran ini.');
        }

        if ($offer->status !== 'matched') {
            throw new \Exception('Transaksi barter hanya dapat diselesaikan jika sudah berstatus matched.');
        }

        return DB::transaction(function () use ($offer, $user) {
            $updated = $this->offerRepository->update($offer->id, [
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update items status to traded
            $this->itemRepository->update($offer->target_item_id, ['status' => 'traded']);

            $offererItemIds = $offer->offererItems()->pluck('items.id')->toArray();
            if (empty($offererItemIds) && $offer->offerer_item_id) {
                $offererItemIds = [$offer->offerer_item_id];
            }
            foreach ($offererItemIds as $offId) {
                $this->itemRepository->update($offId, ['status' => 'traded']);
            }

            // Post system message
            Chat::create([
                'offer_id' => $offer->id,
                'sender_id' => $user->id,
                'message' => 'Transaksi barter telah berhasil diselesaikan secara langsung (COD). Terima kasih telah menggunakan AdaBarter!',
                'type' => 'system',
            ]);

            // Stop any active barter trackings
            \App\Models\BarterTracking::where('offer_id', $offer->id)->update([
                'is_sharing' => false,
                'status' => 'arrived',
            ]);

            return $updated;
        });
    }

    /**
     * Cancel offer:
     * - If pending: only offerer can cancel.
     * - If matched: either participant can cancel if escrow has not been paid yet (escrow_status is WAITING_PAYMENT or null).
     *
     * @param User $user
     * @param int $id
     * @return Offer
     * @throws AuthorizationException
     */
    public function cancelOffer(User $user, int $id): Offer
    {
        /** @var Offer|null $offer */
        $offer = $this->offerRepository->find($id);

        if (!$offer) {
            throw new \Exception('Penawaran tidak ditemukan.');
        }

        // Kasus 1: Penawaran masih berstatus pending
        if ($offer->status === 'pending') {
            if ($offer->offerer_user_id !== $user->id) {
                throw new AuthorizationException('Hanya penawar yang dapat membatalkan penawaran ini.');
            }

            return $this->offerRepository->update($offer->id, [
                'status' => 'cancelled',
            ]);
        }

        // Kasus 2: Penawaran sudah disepakati (matched)
        if ($offer->status === 'matched') {
            if (!in_array($user->id, [$offer->offerer_user_id, $offer->target_user_id], true)) {
                throw new AuthorizationException('Hanya pihak yang terlibat dalam penawaran ini yang dapat membatalkannya.');
            }

            // Jika transaksi menggunakan rekening bersama (escrow), hanya bisa dibatalkan jika belum dibayar (WAITING_PAYMENT atau null)
            if ($offer->isEscrow() && !in_array($offer->escrow_status, [EscrowStatus::WAITING_PAYMENT, null], true)) {
                throw new \Exception('Transaksi tidak dapat dibatalkan langsung karena dana telah disetorkan. Silakan ajukan komplain/sengketa.');
            }

            return DB::transaction(function () use ($offer, $user) {
                // 1. Batalkan pembayaran pending dan kembalikan voucher jika ada
                $pendingPayments = Payment::where('offer_id', $offer->id)
                    ->where('status', 'pending')
                    ->get();

                foreach ($pendingPayments as $payment) {
                    $payment->update(['status' => 'cancelled']);

                    if ($payment->voucher_claim_id) {
                        VoucherClaim::where('id', $payment->voucher_claim_id)->update([
                            'is_used' => false,
                            'used_at' => null,
                            'offer_id' => null,
                        ]);
                    }
                }

                // 2. Transisi status escrow ke EXPIRED jika masih WAITING_PAYMENT
                $updateData = ['status' => 'cancelled'];
                if ($offer->escrow_status === EscrowStatus::WAITING_PAYMENT) {
                    $updateData['escrow_status'] = EscrowStatus::EXPIRED;
                }

                $updated = $this->offerRepository->update($offer->id, $updateData);

                // 3. Catat notifikasi sistem ke chat negosiasi
                Chat::create([
                    'offer_id' => $offer->id,
                    'sender_id' => $user->id,
                    'message' => "Kesepakatan barter telah dibatalkan oleh {$user->name}.",
                    'type' => 'system',
                ]);

                return $updated;
            });
        }

        throw new \Exception('Penawaran dengan status [' . $offer->status . '] tidak dapat dibatalkan.');
    }
}

