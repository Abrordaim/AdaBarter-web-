<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\MeetingPoint;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MeetingPointService
{
    /**
     * Mengambil data titik temu aktif (locked), usulan pending (proposed), dan riwayat untuk suatu penawaran barter.
     */
    public function getMeetingPointDetails(User $user, int $offerId): array
    {
        /** @var Offer|null $offer */
        $offer = Offer::with(['offerer', 'targetOwner'])->find($offerId);

        if (!$offer) {
            throw new \Exception('Transaksi barter tidak ditemukan.');
        }

        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id && !$user->isAdmin()) {
            throw new AuthorizationException('Anda bukan peserta dalam transaksi barter ini.');
        }

        $locked = $offer->meetingPoints()
            ->with(['proposer', 'agreer'])
            ->where('status', 'locked')
            ->latest('locked_at')
            ->first();

        $pending = $offer->meetingPoints()
            ->with(['proposer'])
            ->where('status', 'proposed')
            ->latest()
            ->first();

        $history = $offer->meetingPoints()
            ->with(['proposer', 'agreer'])
            ->where('status', 'superseded')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'offer_id'       => $offer->id,
            'offer_status'   => $offer->status,
            'is_matched'     => $offer->status === 'matched',
            'locked'         => $locked,
            'pending'        => $pending,
            'history'        => $history,
            'current_user_id'=> $user->id,
            'can_propose'    => $offer->status === 'matched',
            'can_agree'      => $pending && $pending->proposed_by !== $user->id && $offer->status === 'matched',
        ];
    }

    /**
     * Mengusulkan titik temu baru.
     */
    public function propose(User $user, int $offerId, array $data): MeetingPoint
    {
        return DB::transaction(function () use ($user, $offerId, $data) {
            /** @var Offer $offer */
            $offer = Offer::where('id', $offerId)->lockForUpdate()->firstOrFail();

            $this->validateOfferEligibility($user, $offer);

            // Tandai usulan pending sebelumnya (jika ada) sebagai superseded
            MeetingPoint::where('offer_id', $offer->id)
                ->where('status', 'proposed')
                ->update(['status' => 'superseded']);

            /** @var MeetingPoint $meetingPoint */
            $meetingPoint = MeetingPoint::create([
                'offer_id'    => $offer->id,
                'proposed_by' => $user->id,
                'latitude'    => $data['latitude'],
                'longitude'   => $data['longitude'],
                'place_name'  => $data['place_name'],
                'address'     => $data['address'] ?? null,
                'status'      => 'proposed',
                'notes'       => $data['notes'] ?? null,
            ]);

            $systemMessage = "📍 {$user->name} mengusulkan titik temu di {$meetingPoint->place_name}.";

            Chat::create([
                'offer_id'  => $offer->id,
                'sender_id' => $user->id,
                'message'   => $systemMessage,
                'type'      => 'system',
            ]);

            return $meetingPoint->load(['proposer']);
        });
    }

    /**
     * Menyetujui usulan titik temu (menjadi locked).
     */
    public function agree(User $user, int $offerId, int $meetingPointId): MeetingPoint
    {
        return DB::transaction(function () use ($user, $offerId, $meetingPointId) {
            /** @var Offer $offer */
            $offer = Offer::where('id', $offerId)->lockForUpdate()->firstOrFail();

            $this->validateOfferEligibility($user, $offer);

            /** @var MeetingPoint $meetingPoint */
            $meetingPoint = MeetingPoint::where('id', $meetingPointId)
                ->where('offer_id', $offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($meetingPoint->status !== 'proposed') {
                throw new \Exception('Usulan titik temu ini sudah tidak aktif atau sudah diproses.');
            }

            if ($meetingPoint->proposed_by === $user->id && !$user->isAdmin()) {
                throw new AuthorizationException('Anda tidak dapat menyetujui usulan titik temu yang Anda ajukan sendiri.');
            }

            // Jika sebelumnya sudah ada titik temu yang locked, beralih ke superseded
            MeetingPoint::where('offer_id', $offer->id)
                ->where('status', 'locked')
                ->where('id', '!=', $meetingPoint->id)
                ->update(['status' => 'superseded']);

            // Kunci titik temu yang disetujui
            $meetingPoint->update([
                'status'             => 'locked',
                'agreed_by'          => $user->id,
                'agreed_by_other_at' => now(),
                'locked_at'          => now(),
            ]);

            $systemMessage = "🔒 Titik temu telah disepakati & dikunci: {$meetingPoint->place_name}. Kedua pihak diharapkan hadir tepat waktu.";

            Chat::create([
                'offer_id'  => $offer->id,
                'sender_id' => $user->id,
                'message'   => $systemMessage,
                'type'      => 'system',
            ]);

            return $meetingPoint->fresh(['proposer', 'agreer']);
        });
    }

    /**
     * Mengusulkan titik temu tandingan / alternatif (counter proposal).
     */
    public function counter(User $user, int $offerId, array $data): MeetingPoint
    {
        return DB::transaction(function () use ($user, $offerId, $data) {
            /** @var Offer $offer */
            $offer = Offer::where('id', $offerId)->lockForUpdate()->firstOrFail();

            $this->validateOfferEligibility($user, $offer);

            // Tandai usulan pending lawan sebagai superseded
            MeetingPoint::where('offer_id', $offer->id)
                ->where('status', 'proposed')
                ->update(['status' => 'superseded']);

            /** @var MeetingPoint $meetingPoint */
            $meetingPoint = MeetingPoint::create([
                'offer_id'    => $offer->id,
                'proposed_by' => $user->id,
                'latitude'    => $data['latitude'],
                'longitude'   => $data['longitude'],
                'place_name'  => $data['place_name'],
                'address'     => $data['address'] ?? null,
                'status'      => 'proposed',
                'notes'       => $data['notes'] ?? null,
            ]);

            $systemMessage = "🔄 {$user->name} mengusulkan perubahan titik temu ke {$meetingPoint->place_name}.";

            Chat::create([
                'offer_id'  => $offer->id,
                'sender_id' => $user->id,
                'message'   => $systemMessage,
                'type'      => 'system',
            ]);

            return $meetingPoint->load(['proposer']);
        });
    }

    /**
     * Validasi kelayakan transaksi barter untuk titik temu.
     */
    protected function validateOfferEligibility(User $user, Offer $offer): void
    {
        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id && !$user->isAdmin()) {
            throw new AuthorizationException('Hanya peserta transaksi yang dapat mengelola titik temu.');
        }

        if ($offer->status !== 'matched') {
            throw new \Exception('Titik temu hanya dapat dikelola saat transaksi barter berstatus matched.');
        }
    }
}
