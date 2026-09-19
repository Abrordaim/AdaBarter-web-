<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
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
     * Submit a barter or tukar tambah offer.
     *
     * @param User $user
     * @param array $data
     * @return Offer
     * @throws ValidationException
     */
    public function createOffer(User $user, array $data): Offer
    {
        $offererItemId = (int) $data['offerer_item_id'];
        $targetItemId = (int) $data['target_item_id'];

        /** @var Item|null $offererItem */
        $offererItem = $this->itemRepository->find($offererItemId);
        /** @var Item|null $targetItem */
        $targetItem = $this->itemRepository->find($targetItemId);

        if (!$offererItem || $offererItem->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'offerer_item_id' => ['Barang penawar tidak valid atau bukan milik Anda.'],
            ]);
        }

        if ($offererItem->status !== 'active') {
            throw ValidationException::withMessages([
                'offerer_item_id' => ['Barang yang Anda tawarkan tidak sedang aktif.'],
            ]);
        }

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

        // Check if there's already a pending offer for these exact two items
        if ($this->offerRepository->existsPendingOffer($offererItemId, $targetItemId)) {
            throw ValidationException::withMessages([
                'target_item_id' => ['Anda sudah memiliki penawaran barter yang sedang diproses untuk barang ini.'],
            ]);
        }

        $offerData = [
            'offerer_user_id' => $user->id,
            'offerer_item_id' => $offererItemId,
            'target_user_id' => $targetItem->user_id,
            'target_item_id' => $targetItemId,
            'cash_supplement' => $data['cash_supplement'] ?? null,
            'cash_supplement_by' => $data['cash_supplement_by'] ?? null,
            'status' => 'pending',
            'offerer_approved' => true,
            'target_approved' => false,
        ];

        return $this->offerRepository->create($offerData);
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
            $updated = $this->offerRepository->update($offer->id, [
                'target_approved' => true,
                'status' => 'matched',
                'matched_at' => now(),
            ]);

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
            $this->itemRepository->update($offer->offerer_item_id, ['status' => 'traded']);
            $this->itemRepository->update($offer->target_item_id, ['status' => 'traded']);

            // Post system message
            Chat::create([
                'offer_id' => $offer->id,
                'sender_id' => $user->id,
                'message' => 'Transaksi barter telah berhasil diselesaikan secara langsung (COD). Terima kasih telah menggunakan AdaBarter!',
                'type' => 'system',
            ]);

            return $updated;
        });
    }

    /**
     * Offerer cancels pending offer.
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

        if ($offer->offerer_user_id !== $user->id) {
            throw new AuthorizationException('Hanya penawar yang dapat membatalkan penawaran ini.');
        }

        if ($offer->status !== 'pending') {
            throw new \Exception('Penawaran hanya dapat dibatalkan saat masih berstatus pending.');
        }

        return $this->offerRepository->update($offer->id, [
            'status' => 'cancelled',
        ]);
    }
}
