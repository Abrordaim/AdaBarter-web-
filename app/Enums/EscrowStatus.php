<?php

namespace App\Enums;

/**
 * Status lifecycle escrow untuk pembayaran selisih barter AdaBarter.
 */
enum EscrowStatus: string
{
    // Menunggu pembayar (payer) menyelesaikan pembayaran di payment gateway
    case WAITING_PAYMENT = 'WAITING_PAYMENT';

    // Pembayaran sukses (settlement), dana tertahan aman di platform
    case HELD = 'HELD';

    // Menunggu konfirmasi serah terima fisik barang dari kedua pihak
    case WAITING_CONFIRMATION = 'WAITING_CONFIRMATION';

    // Transaksi selesai, dana selisih telah dilepaskan ke dompet receiver
    case COMPLETED = 'COMPLETED';

    // Salah satu pihak mengajukan komplain/sanggahan, pelepasan otomatis dibekukan
    case DISPUTED = 'DISPUTED';

    // Admin memutuskan pengembalian dana (refund) ke pihak pembayar
    case REFUNDED = 'REFUNDED';

    // Waktu pembayaran habis tanpa penyelesaian dari payer
    case EXPIRED = 'EXPIRED';

    /**
     * Label ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::WAITING_PAYMENT => 'Menunggu Pembayaran',
            self::HELD => 'Dana Tertahan (Siap Serah Terima)',
            self::WAITING_CONFIRMATION => 'Menunggu Konfirmasi',
            self::COMPLETED => 'Selesai',
            self::DISPUTED => 'Dalam Sengketa / Dispute',
            self::REFUNDED => 'Dana Dikembalikan (Refund)',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }

    /**
     * Warna badge untuk UI tampilan mobile & web.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::WAITING_PAYMENT => 'warning',
            self::HELD, self::WAITING_CONFIRMATION => 'info',
            self::COMPLETED => 'success',
            self::DISPUTED, self::REFUNDED => 'danger',
            self::EXPIRED => 'neutral',
        };
    }

    /**
     * Cek apakah status sudah final / terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REFUNDED, self::EXPIRED], true);
    }
}
