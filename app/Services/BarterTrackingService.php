<?php

namespace App\Services;

use App\Models\BarterTracking;
use App\Models\Chat;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarterTrackingService
{
    /**
     * Ambil data status pelacakan real-time untuk penawaran barter.
     */
    public function getTrackingStatus(User $user, int $offerId): array
    {
        /** @var Offer|null $offer */
        $offer = Offer::with(['offerer', 'targetOwner', 'lockedMeetingPoint'])->find($offerId);

        if (!$offer) {
            throw new \Exception('Transaksi barter tidak ditemukan.');
        }

        $this->validateParticipant($user, $offer);

        if ($offer->status !== 'matched') {
            throw ValidationException::withMessages([
                'offer' => ['Pelacakan hanya dapat dilakukan pada transaksi yang berstatus matched.'],
            ]);
        }

        $lockedMeetingPoint = $offer->lockedMeetingPoint;
        if (!$lockedMeetingPoint) {
            throw ValidationException::withMessages([
                'offer' => ['Titik temu belum disepakati (locked). Harap sepakati titik temu terlebih dahulu.'],
            ]);
        }

        $isOfferer = $offer->offerer_user_id === $user->id;
        $partner = $isOfferer ? $offer->targetOwner : $offer->offerer;

        /** @var BarterTracking|null $myTracking */
        $myTracking = BarterTracking::where('offer_id', $offer->id)
            ->where('user_id', $user->id)
            ->first();

        /** @var BarterTracking|null $partnerTracking */
        $partnerTracking = BarterTracking::where('offer_id', $offer->id)
            ->where('user_id', $partner->id)
            ->first();

        // Calculate distance between users if both are sharing and have coordinates
        $distanceBetweenUsers = null;
        if (
            $myTracking && $myTracking->latitude && $myTracking->longitude &&
            $partnerTracking && $partnerTracking->latitude && $partnerTracking->longitude &&
            $partnerTracking->is_sharing
        ) {
            $distanceBetweenUsers = BarterTracking::calculateDistance(
                $myTracking->latitude,
                $myTracking->longitude,
                $partnerTracking->latitude,
                $partnerTracking->longitude
            );
        }

        return [
            'offer_id'       => $offer->id,
            'offer_status'   => $offer->status,
            'meeting_point'  => [
                'id'         => $lockedMeetingPoint->id,
                'place_name' => $lockedMeetingPoint->place_name,
                'address'    => $lockedMeetingPoint->address,
                'latitude'   => $lockedMeetingPoint->latitude,
                'longitude'  => $lockedMeetingPoint->longitude,
                'meet_at'    => $lockedMeetingPoint->meet_at,
                'maps_url'   => $lockedMeetingPoint->maps_url,
            ],
            'my_tracking'    => $myTracking ? [
                'latitude'                  => $myTracking->latitude,
                'longitude'                 => $myTracking->longitude,
                'status'                    => $myTracking->status,
                'is_sharing'                => $myTracking->is_sharing,
                'distance_to_target_meters' => $myTracking->distance_to_target_meters,
                'heading'                   => $myTracking->heading,
                'speed'                     => $myTracking->speed,
                'last_seen_at'              => $myTracking->last_seen_at,
            ] : null,
            'partner'        => [
                'id'         => $partner->id,
                'name'       => $partner->name,
                'avatar_url' => $partner->avatar_url,
            ],
            'partner_tracking' => ($partnerTracking && $partnerTracking->is_sharing) ? [
                'latitude'                  => $partnerTracking->latitude,
                'longitude'                 => $partnerTracking->longitude,
                'status'                    => $partnerTracking->status,
                'is_sharing'                => $partnerTracking->is_sharing,
                'distance_to_target_meters' => $partnerTracking->distance_to_target_meters,
                'heading'                   => $partnerTracking->heading,
                'speed'                     => $partnerTracking->speed,
                'last_seen_at'              => $partnerTracking->last_seen_at,
            ] : ($partnerTracking ? [
                'status'     => $partnerTracking->status,
                'is_sharing' => false,
            ] : null),
            'distance_between_users_meters' => $distanceBetweenUsers,
        ];
    }

    /**
     * Memperbarui lokasi GPS pengguna dan status perjalanan.
     */
    public function updateLocation(User $user, int $offerId, array $data): BarterTracking
    {
        return DB::transaction(function () use ($user, $offerId, $data) {
            /** @var Offer $offer */
            $offer = Offer::with(['lockedMeetingPoint'])->where('id', $offerId)->lockForUpdate()->firstOrFail();

            $this->validateParticipant($user, $offer);

            if ($offer->status !== 'matched') {
                throw ValidationException::withMessages([
                    'offer' => ['Pelacakan hanya dapat dilakukan pada transaksi yang berstatus matched.'],
                ]);
            }

            $lockedMeetingPoint = $offer->lockedMeetingPoint;
            if (!$lockedMeetingPoint) {
                throw ValidationException::withMessages([
                    'offer' => ['Titik temu belum disepakati (locked).'],
                ]);
            }

            $lat = (float) $data['latitude'];
            $lng = (float) $data['longitude'];

            // Calculate distance to meeting point
            $distToTarget = BarterTracking::calculateDistance(
                $lat,
                $lng,
                $lockedMeetingPoint->latitude,
                $lockedMeetingPoint->longitude
            );

            // Determine status
            $requestedStatus = $data['status'] ?? 'otw';
            if ($distToTarget <= 50 && $requestedStatus === 'otw') {
                $status = 'arrived';
            } else {
                $status = in_array($requestedStatus, ['idle', 'otw', 'arrived'], true) ? $requestedStatus : 'otw';
            }

            /** @var BarterTracking|null $existing */
            $existing = BarterTracking::where('offer_id', $offer->id)
                ->where('user_id', $user->id)
                ->first();

            $prevStatus = $existing ? $existing->status : 'idle';

            // Send notification to chat if status transitioned
            if ($status === 'otw' && $prevStatus !== 'otw') {
                Chat::create([
                    'offer_id'  => $offer->id,
                    'sender_id' => $user->id,
                    'message'   => "🚗 {$user->name} mulai berangkat menuju lokasi pertemuan.",
                    'type'      => 'system',
                ]);
            } elseif ($status === 'arrived' && $prevStatus !== 'arrived') {
                Chat::create([
                    'offer_id'  => $offer->id,
                    'sender_id' => $user->id,
                    'message'   => "📍 {$user->name} telah sampai di lokasi pertemuan ({$lockedMeetingPoint->place_name})!",
                    'type'      => 'system',
                ]);
            }

            return BarterTracking::updateOrCreate(
                [
                    'offer_id' => $offer->id,
                    'user_id'  => $user->id,
                ],
                [
                    'latitude'                  => $lat,
                    'longitude'                 => $lng,
                    'status'                    => $status,
                    'is_sharing'                => true,
                    'distance_to_target_meters' => $distToTarget,
                    'heading'                   => isset($data['heading']) ? (float) $data['heading'] : null,
                    'speed'                     => isset($data['speed']) ? (float) $data['speed'] : null,
                    'last_seen_at'              => now(),
                ]
            );
        });
    }

    /**
     * Menghentikan pembagian lokasi GPS (stop tracking).
     */
    public function stopTracking(User $user, int $offerId): BarterTracking
    {
        return DB::transaction(function () use ($user, $offerId) {
            /** @var Offer $offer */
            $offer = Offer::where('id', $offerId)->firstOrFail();

            $this->validateParticipant($user, $offer);

            /** @var BarterTracking $tracking */
            $tracking = BarterTracking::updateOrCreate(
                [
                    'offer_id' => $offer->id,
                    'user_id'  => $user->id,
                ],
                [
                    'status'     => 'idle',
                    'is_sharing' => false,
                ]
            );

            Chat::create([
                'offer_id'  => $offer->id,
                'sender_id' => $user->id,
                'message'   => "{$user->name} menghentikan pembagian lokasi pelacakan.",
                'type'      => 'system',
            ]);

            return $tracking;
        });
    }

    /**
     * Memeriksa otorisasi apakah user adalah peserta transaksi barter.
     */
    private function validateParticipant(User $user, Offer $offer): void
    {
        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id && !$user->isAdmin()) {
            throw new AuthorizationException('Anda bukan peserta dalam transaksi barter ini.');
        }
    }
}
