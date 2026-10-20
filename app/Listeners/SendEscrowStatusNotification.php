<?php

namespace App\Listeners;

use App\Enums\EscrowStatus;
use App\Events\EscrowStatusChanged;
use App\Models\Chat;

class SendEscrowStatusNotification
{
    /**
     * Kirim notifikasi sistem in-app ke kedua pihak melalui ruang obrolan (Chat) transaksi barter.
     */
    public function handle(EscrowStatusChanged $event): void
    {
        $offer = $event->offer;
        $status = $event->newStatus;

        $amountFormatted = number_format($offer->price_difference, 0, ',', '.');

        $message = match ($status) {
            EscrowStatus::WAITING_PAYMENT => "Tagihan selisih barter sebesar Rp {$amountFormatted} telah dibuat. Menunggu pembayaran dari pembayar.",
            EscrowStatus::HELD => "Pembayaran selisih Rp {$amountFormatted} BERHASIL dan aman ditahan di platform (Escrow). Silakan kedua pihak bertemu untuk serah terima fisik barang.",
            EscrowStatus::WAITING_CONFIRMATION => "Dana aman di escrow. Menunggu konfirmasi serah terima barang dari kedua pihak.",
            EscrowStatus::COMPLETED => "Transaksi barter selesai! Dana selisih Rp {$amountFormatted} telah berhasil dilepaskan ke dompet penerima.",
            EscrowStatus::DISPUTED => "⚠️ Sanggahan (Dispute) diajukan pada transaksi ini. Pelepasan dana otomatis DIBEKUKAN sementara untuk peninjauan admin.",
            EscrowStatus::REFUNDED => "Proses sengketa selesai. Dana selisih telah dikembalikan ke saldo dompet pembayar.",
            EscrowStatus::EXPIRED => "Batas waktu pembayaran selisih telah kedaluwarsa.",
        };

        // Rekam pesan sistem ke riwayat obrolan negosiasi
        Chat::create([
            'offer_id' => $offer->id,
            'sender_id' => $offer->payer_id ?: $offer->offerer_user_id,
            'message' => $message,
            'type' => 'system',
        ]);
    }
}
