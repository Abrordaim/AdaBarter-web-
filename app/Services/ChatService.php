<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Offer;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Repositories\Contracts\OfferRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;

class ChatService
{
    public function __construct(
        protected ChatRepositoryInterface $chatRepository,
        protected OfferRepositoryInterface $offerRepository
    ) {}

    /**
     * Get list of conversations for the current user.
     *
     * @param User $user
     * @return mixed
     */
    public function getUserConversations(User $user)
    {
        return $this->offerRepository->getMatchedOffersForUser($user->id);
    }

    /**
     * Get chat messages for an offer.
     *
     * @param User $user
     * @param int $offerId
     * @return mixed
     * @throws AuthorizationException
     */
    public function getOfferMessages(User $user, int $offerId)
    {
        /** @var Offer|null $offer */
        $offer = $this->offerRepository->find($offerId);

        if (!$offer) {
            throw new \Exception('Ruang obrolan tidak ditemukan.');
        }

        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id) {
            throw new AuthorizationException('Anda tidak memiliki akses ke ruang obrolan ini.');
        }

        if (!in_array($offer->status, ['matched', 'completed'])) {
            throw new \Exception('Ruang chat negosiasi hanya terbuka jika penawaran sudah disetujui kedua belah pihak (Matched).');
        }

        // Mark unread messages sent by the other party as read
        $this->chatRepository->markAsRead($offerId, $user->id);

        return $this->chatRepository->getByOfferId($offerId);
    }

    /**
     * Send a message in the negotiation room.
     *
     * @param User $user
     * @param int $offerId
     * @param string $message
     * @param string $type
     * @return Chat
     * @throws AuthorizationException
     */
    public function sendMessage(User $user, int $offerId, string $message, string $type = 'text'): Chat
    {
        /** @var Offer|null $offer */
        $offer = $this->offerRepository->find($offerId);

        if (!$offer) {
            throw new \Exception('Ruang obrolan tidak ditemukan.');
        }

        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk mengirim pesan di ruang obrolan ini.');
        }

        if ($offer->status !== 'matched') {
            throw new \Exception('Pesan hanya dapat dikirim jika barter berstatus matched.');
        }

        return $this->chatRepository->create([
            'offer_id' => $offerId,
            'sender_id' => $user->id,
            'message' => $message,
            'type' => $type,
        ]);
    }
}
