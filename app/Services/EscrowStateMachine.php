<?php

namespace App\Services;

use App\Enums\EscrowStatus;
use App\Events\EscrowStatusChanged;
use App\Exceptions\InvalidEscrowTransitionException;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

class EscrowStateMachine
{
    /**
     * Matriks transisi status escrow yang sah.
     * Alur:
     * - WAITING_PAYMENT -> HELD, EXPIRED
     * - HELD -> WAITING_CONFIRMATION, DISPUTED
     * - WAITING_CONFIRMATION -> COMPLETED, DISPUTED
     * - DISPUTED -> COMPLETED, REFUNDED
     * - Terminal states (COMPLETED, REFUNDED, EXPIRED) -> tidak memiliki transisi lanjutan
     */
    private const array ALLOWED_TRANSITIONS = [
        EscrowStatus::WAITING_PAYMENT->value => [
            EscrowStatus::HELD->value,
            EscrowStatus::EXPIRED->value,
        ],
        EscrowStatus::HELD->value => [
            EscrowStatus::WAITING_CONFIRMATION->value,
            EscrowStatus::COMPLETED->value,
            EscrowStatus::DISPUTED->value,
        ],
        EscrowStatus::WAITING_CONFIRMATION->value => [
            EscrowStatus::COMPLETED->value,
            EscrowStatus::DISPUTED->value,
        ],
        EscrowStatus::DISPUTED->value => [
            EscrowStatus::COMPLETED->value,
            EscrowStatus::REFUNDED->value,
        ],
        EscrowStatus::COMPLETED->value => [],
        EscrowStatus::REFUNDED->value => [],
        EscrowStatus::EXPIRED->value => [],
    ];

    /**
     * Memeriksa apakah transisi dari status lama ke status baru sah.
     */
    public static function canTransition(?EscrowStatus $from, EscrowStatus $to): bool
    {
        // Inisialisasi awal saat pertama kali masuk ke WAITING_PAYMENT
        if ($from === null) {
            return $to === EscrowStatus::WAITING_PAYMENT;
        }

        $allowedNext = self::ALLOWED_TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowedNext, true);
    }

    /**
     * Melakukan transisi status escrow secara atomik dan thread-safe dengan lockForUpdate.
     *
     * @param Offer $offer
     * @param EscrowStatus $targetStatus
     * @param array $additionalAttributes Atribut tambahan yang ingin diupdate bersamaan (misal: confirm_deadline_at, released_at)
     * @return Offer
     * @throws InvalidEscrowTransitionException
     */
    public function transition(Offer $offer, EscrowStatus $targetStatus, array $additionalAttributes = []): Offer
    {
        return DB::transaction(function () use ($offer, $targetStatus, $additionalAttributes) {
            /** @var Offer $lockedOffer */
            $lockedOffer = Offer::where('id', $offer->id)->lockForUpdate()->firstOrFail();

            $currentStatus = $lockedOffer->escrow_status;

            if (!self::canTransition($currentStatus, $targetStatus)) {
                throw new InvalidEscrowTransitionException(
                    $currentStatus,
                    $targetStatus,
                    "Transisi escrow ditolak: dari status [" . ($currentStatus?->value ?? 'NULL') . "] tidak diperbolehkan pindah ke [{$targetStatus->value}]."
                );
            }

            // Siapkan atribut update
            $updatePayload = array_merge($additionalAttributes, [
                'escrow_status' => $targetStatus->value,
            ]);

            // Jika status berubah ke HELD dan belum memiliki handover_code, buat kode unik 6 digit
            if ($targetStatus === EscrowStatus::HELD && empty($lockedOffer->handover_code)) {
                $updatePayload['handover_code'] = sprintf('%06d', random_int(100000, 999999));
            }

            // Jika status COMPLETED, tandai waktu pelepasan dana
            if ($targetStatus === EscrowStatus::COMPLETED && empty($lockedOffer->released_at)) {
                $updatePayload['released_at'] = now();
            }

            $lockedOffer->update($updatePayload);

            // Trigger event notifikasi status escrow
            event(new EscrowStatusChanged($lockedOffer, $currentStatus, $targetStatus));

            return $lockedOffer->refresh();
        });
    }
}
