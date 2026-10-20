<?php

namespace App\Services;

use App\Enums\EscrowStatus;
use App\Models\Chat;
use App\Models\Dispute;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EscrowService
{
    public function __construct(
        protected EscrowStateMachine $stateMachine
    ) {}

    /**
     * Konfirmasi serah terima fisik barang oleh salah satu pihak (Payer atau Receiver).
     * Jika kedua pihak telah mengonfirmasi, dana escrow akan otomatis langsung dilepas (released).
     *
     * @param User $user
     * @param Offer $offer
     * @param string|null $handoverCode Kode 6-digit rahasia dari payer (opsional atau wajib bagi receiver saat bertemu)
     * @return array{success: bool, message: string, offer: Offer, is_released: bool}
     * @throws AuthorizationException
     */
    public function confirmReceived(User $user, Offer $offer, ?string $handoverCode = null): array
    {
        // 1. Otorisasi: Hanya payer atau receiver yang berhak mengonfirmasi
        if ($user->id !== $offer->payer_id && $user->id !== $offer->receiver_id) {
            throw new AuthorizationException('Anda bukan peserta sah dalam transaksi barter ber-escrow ini.');
        }

        // 2. Validasi metode pembayaran
        if (!$offer->isEscrow()) {
            throw new DomainException('Transaksi barter ini tidak menggunakan rekening bersama (escrow).');
        }

        // 3. Validasi status escrow
        if (!in_array($offer->escrow_status, [EscrowStatus::HELD, EscrowStatus::WAITING_CONFIRMATION], true)) {
            throw new DomainException("Konfirmasi tidak dapat dilakukan pada status escrow saat ini [{$offer->escrow_status?->value}].");
        }

        // 4. Periksa apakah ada sengketa (dispute) yang sedang terbuka
        if ($offer->disputes()->where('status', 'open')->exists()) {
            throw new DomainException('Transaksi ini sedang dalam penanganan sengketa (Dispute). Konfirmasi serah terima dibekukan.');
        }

        $isPayer = ($user->id === $offer->payer_id);
        $isReceiver = ($user->id === $offer->receiver_id);

        // Validasi kode serah terima receiver di luar transaction agar kenaikan counter tersimpan
        if ($isReceiver && !empty($handoverCode)) {
            if ($offer->handover_attempts >= 5) {
                throw new DomainException('Batas maksimal percobaan kode serah terima telah habis (5x). Silakan hubungi admin untuk mediasi.');
            }

            if (trim($handoverCode) !== $offer->handover_code) {
                $offer->increment('handover_attempts');
                $remaining = max(0, 5 - $offer->handover_attempts);
                throw new DomainException("Kode serah terima salah. Sisa kesempatan: {$remaining}x percobaan.");
            }
        }

        return DB::transaction(function () use ($user, $offer, $handoverCode, $isPayer, $isReceiver) {
            /** @var Offer $lockedOffer */
            $lockedOffer = Offer::where('id', $offer->id)->lockForUpdate()->firstOrFail();
            $now = now();

            // Skenario A: Receiver memasukkan kode serah terima 6 digit dari Payer saat tatap muka
            if ($isReceiver && !empty($handoverCode)) {
                // Kode telah tervalidasi cocok!
                $lockedOffer->update([
                    'receiver_confirmed_at' => $now,
                    'payer_confirmed_at' => $lockedOffer->payer_confirmed_at ?: $now,
                ]);

                // Langsung lepaskan dana ke dompet receiver
                $releasedOffer = $this->releaseFunds($lockedOffer, 'handover_code_verified');

                return [
                    'success' => true,
                    'message' => 'Kode serah terima terverifikasi valid! Barter selesai dan dana selisih telah dikreditkan ke dompet Anda.',
                    'offer' => $releasedOffer,
                    'is_released' => true,
                ];
            }

            // Skenario B: Konfirmasi mandiri melalui tombol di aplikasi
            if ($isPayer) {
                if ($lockedOffer->payer_confirmed_at !== null) {
                    throw new DomainException('Anda sudah pernah mengonfirmasi penerimaan barang sebelumnya.');
                }
                $lockedOffer->update(['payer_confirmed_at' => $now]);
            } elseif ($isReceiver) {
                if ($lockedOffer->receiver_confirmed_at !== null) {
                    throw new DomainException('Anda sudah pernah mengonfirmasi penerimaan barang sebelumnya.');
                }
                $lockedOffer->update(['receiver_confirmed_at' => $now]);
            }

            $lockedOffer->refresh();

            // Cek apakah KEDUA pihak sudah mengonfirmasi
            if ($lockedOffer->payer_confirmed_at !== null && $lockedOffer->receiver_confirmed_at !== null) {
                $releasedOffer = $this->releaseFunds($lockedOffer, 'both_confirmed');

                return [
                    'success' => true,
                    'message' => 'Kedua pihak telah mengonfirmasi serah terima! Dana selisih resmi dilepaskan ke dompet penerima.',
                    'offer' => $releasedOffer,
                    'is_released' => true,
                ];
            }

            // Baru satu pihak yang konfirmasi
            $waitingFor = $isPayer ? ($lockedOffer->receiver?->name ?: 'Penerima') : ($lockedOffer->payer?->name ?: 'Pembayar');
            
            Chat::create([
                'offer_id' => $lockedOffer->id,
                'sender_id' => $user->id,
                'message' => "{$user->name} telah mengonfirmasi serah terima barang. Menunggu konfirmasi dari {$waitingFor}.",
                'type' => 'system',
            ]);

            return [
                'success' => true,
                'message' => "Konfirmasi Anda berhasil dicatat. Menunggu konfirmasi dari {$waitingFor}.",
                'offer' => $lockedOffer,
                'is_released' => false,
            ];
        });
    }

    /**
     * Melepaskan dana dari penampungan escrow ke dompet pihak penerima (Receiver).
     *
     * @param Offer $offer
     * @param string $reason
     * @return Offer
     */
    public function releaseFunds(Offer $offer, string $reason = 'manual_confirmation'): Offer
    {
        return DB::transaction(function () use ($offer, $reason) {
            /** @var Offer $lockedOffer */
            $lockedOffer = Offer::where('id', $offer->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedOffer->escrow_status, [EscrowStatus::HELD, EscrowStatus::WAITING_CONFIRMATION], true)) {
                throw new DomainException("Dana tidak dapat dilepaskan pada status escrow [{$lockedOffer->escrow_status?->value}].");
            }

            if ($lockedOffer->released_at !== null) {
                throw new DomainException('Dana transaksi ini sudah pernah dilepaskan sebelumnya.');
            }

            if ($lockedOffer->disputes()->where('status', 'open')->exists()) {
                throw new DomainException('Pelepasan dana ditolak: transaksi sedang dalam sengketa terbuka.');
            }

            // Hitung nominal bersih (dikurangi biaya platform jika ada)
            $platformFee = (int) config('services.midtrans.platform_fee', 0);
            $netAmount = max(0, $lockedOffer->price_difference - $platformFee);

            // Kredit ke dompet receiver lewat ledger append-only
            /** @var User $receiver */
            $receiver = User::findOrFail($lockedOffer->receiver_id);
            $wallet = $receiver->getOrCreateWallet();
            $wallet->credit($netAmount, 'escrow_release', $lockedOffer);

            // Transisi status escrow menjadi COMPLETED
            $this->stateMachine->transition($lockedOffer, EscrowStatus::COMPLETED, [
                'released_at' => now(),
            ]);

            // Tandai status barter selesai dan item menjadi traded
            $lockedOffer->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update status barang target
            Item::where('id', $lockedOffer->target_item_id)->update(['status' => 'traded']);

            // Update status barang penawar (single maupun multi-barang)
            $offererItemIds = $lockedOffer->offererItems()->pluck('items.id')->toArray();
            if (empty($offererItemIds) && $lockedOffer->offerer_item_id) {
                $offererItemIds = [$lockedOffer->offerer_item_id];
            }
            if (!empty($offererItemIds)) {
                Item::whereIn('id', $offererItemIds)->update(['status' => 'traded']);
            }

            return $lockedOffer->refresh();
        });
    }

    /**
     * Mengajukan komplain/sanggahan (dispute) atas transaksi barter ber-escrow.
     * Pelepasan dana otomatis (auto-release) akan langsung dibekukan.
     *
     * @param User $user
     * @param Offer $offer
     * @param string $reason
     * @param string $description
     * @param array<string> $evidencePaths
     * @return Dispute
     * @throws AuthorizationException
     */
    public function openDispute(
        User $user,
        Offer $offer,
        string $reason,
        string $description,
        array $evidencePaths = []
    ): Dispute {
        // 1. Otorisasi: Hanya payer atau receiver yang berhak mengajukan dispute
        if ($user->id !== $offer->payer_id && $user->id !== $offer->receiver_id) {
            throw new AuthorizationException('Anda bukan peserta sah dalam transaksi barter ber-escrow ini.');
        }

        // 2. Validasi metode pembayaran escrow
        if (!$offer->isEscrow()) {
            throw new DomainException('Sengketa hanya berlaku untuk transaksi barter dengan rekening bersama (escrow).');
        }

        // 3. Validasi status escrow: harus HELD atau WAITING_CONFIRMATION
        if (!in_array($offer->escrow_status, [EscrowStatus::HELD, EscrowStatus::WAITING_CONFIRMATION], true)) {
            throw new DomainException("Sengketa tidak dapat diajukan pada status escrow saat ini [{$offer->escrow_status?->value}].");
        }

        return DB::transaction(function () use ($user, $offer, $reason, $description, $evidencePaths) {
            /** @var Offer $lockedOffer */
            $lockedOffer = Offer::where('id', $offer->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedOffer->escrow_status, [EscrowStatus::HELD, EscrowStatus::WAITING_CONFIRMATION], true)) {
                throw new DomainException("Status transaksi telah berubah menjadi [{$lockedOffer->escrow_status?->value}]. Sengketa dibatalkan.");
            }

            if ($lockedOffer->disputes()->where('status', 'open')->exists()) {
                throw new DomainException('Sengketa untuk transaksi ini sudah ada yang terbuka dan sedang dalam peninjauan.');
            }

            // Buat record dispute
            $dispute = Dispute::create([
                'offer_id' => $lockedOffer->id,
                'reported_by' => $user->id,
                'reason' => $reason,
                'description' => $description,
                'evidence_paths' => $evidencePaths,
                'status' => 'open',
            ]);

            // Transisi status escrow menjadi DISPUTED (membekukan auto-release)
            $this->stateMachine->transition($lockedOffer, EscrowStatus::DISPUTED);

            // Buat notifikasi chat sistem
            Chat::create([
                'offer_id' => $lockedOffer->id,
                'sender_id' => $user->id,
                'message' => "Sengketa/komplain resmi diajukan oleh {$user->name}. Alasan: {$reason}. Pelepasan dana otomatis dibekukan menunggu keputusan admin.",
                'type' => 'system',
            ]);

            return $dispute;
        });
    }

    /**
     * Memutuskan penyelesaian sengketa oleh Admin.
     * Pilihan keputusan:
     * - refund_full: Pengembalian dana 100% ke dompet Payer, transaksi barter dibatalkan, status barang dipulihkan.
     * - release: Dana selisih dilepaskan ke dompet Receiver, transaksi barter diselesaikan (completed), barang ditandai traded.
     * - refund_partial: Pengembalian sebagian dana ke Payer dan sisa dana ke Receiver.
     *
     * @param User $admin
     * @param Dispute $dispute
     * @param string $resolution 'refund_full', 'release', atau 'refund_partial'
     * @param int|null $refundAmount Diisi nominal pengembalian ke Payer jika refund_partial
     * @param string|null $adminNote
     * @return Dispute
     * @throws AuthorizationException
     */
    public function resolveDispute(
        User $admin,
        Dispute $dispute,
        string $resolution,
        ?int $refundAmount = null,
        ?string $adminNote = null
    ): Dispute {
        // 1. Otorisasi admin
        if (!$admin->isAdmin()) {
            throw new AuthorizationException('Hanya admin yang memiliki wewenang untuk menyelesaikan sengketa barter.');
        }

        // 2. Validasi status sengketa
        if (!$dispute->isOpen()) {
            throw new DomainException('Sengketa ini sudah pernah diselesaikan sebelumnya.');
        }

        // 3. Validasi tipe keputusan
        if (!in_array($resolution, ['refund_full', 'release', 'refund_partial'], true)) {
            throw new DomainException("Tipe keputusan mediasi tidak valid [{$resolution}].");
        }

        return DB::transaction(function () use ($admin, $dispute, $resolution, $refundAmount, $adminNote) {
            /** @var Dispute $lockedDispute */
            $lockedDispute = Dispute::where('id', $dispute->id)->lockForUpdate()->firstOrFail();

            if (!$lockedDispute->isOpen()) {
                throw new DomainException('Sengketa ini telah diselesaikan oleh sesi lain.');
            }

            /** @var Offer $lockedOffer */
            $lockedOffer = Offer::where('id', $lockedDispute->offer_id)->lockForUpdate()->firstOrFail();

            if ($lockedOffer->escrow_status !== EscrowStatus::DISPUTED) {
                throw new DomainException("Status transaksi saat ini [{$lockedOffer->escrow_status?->value}], bukan DISPUTED.");
            }

            $diff = (int) $lockedOffer->price_difference;

            if ($resolution === 'refund_full') {
                $actualRefund = $diff;

                // Kredit ke dompet payer
                /** @var User $payer */
                $payer = User::findOrFail($lockedOffer->payer_id);
                $payer->getOrCreateWallet()->credit($actualRefund, 'escrow_refund_dispute', $lockedOffer);

                // Transisi escrow menjadi REFUNDED
                $this->stateMachine->transition($lockedOffer, EscrowStatus::REFUNDED);

                // Update status tawaran & pulihkan status barang ke active
                $lockedOffer->update([
                    'status' => 'cancelled',
                ]);

                Item::where('id', $lockedOffer->target_item_id)->update(['status' => 'active']);
                $offererItemIds = $lockedOffer->offererItems()->pluck('items.id')->toArray();
                if (empty($offererItemIds) && $lockedOffer->offerer_item_id) {
                    $offererItemIds = [$lockedOffer->offerer_item_id];
                }
                if (!empty($offererItemIds)) {
                    Item::whereIn('id', $offererItemIds)->update(['status' => 'active']);
                }

                $decisionText = 'Dana selisih Rp ' . number_format($actualRefund, 0, ',', '.') . ' dikembalikan penuh ke dompet Payer.';
            } elseif ($resolution === 'release') {
                $actualRefund = 0;
                $platformFee = (int) config('services.midtrans.platform_fee', 0);
                $releaseAmount = max(0, $diff - $platformFee);

                // Kredit ke dompet receiver
                /** @var User $receiver */
                $receiver = User::findOrFail($lockedOffer->receiver_id);
                $receiver->getOrCreateWallet()->credit($releaseAmount, 'escrow_release_dispute', $lockedOffer);

                // Transisi escrow menjadi COMPLETED
                $this->stateMachine->transition($lockedOffer, EscrowStatus::COMPLETED, [
                    'released_at' => now(),
                ]);

                // Update status tawaran & barang traded
                $lockedOffer->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                Item::where('id', $lockedOffer->target_item_id)->update(['status' => 'traded']);
                $offererItemIds = $lockedOffer->offererItems()->pluck('items.id')->toArray();
                if (empty($offererItemIds) && $lockedOffer->offerer_item_id) {
                    $offererItemIds = [$lockedOffer->offerer_item_id];
                }
                if (!empty($offererItemIds)) {
                    Item::whereIn('id', $offererItemIds)->update(['status' => 'traded']);
                }

                $decisionText = 'Dana selisih Rp ' . number_format($releaseAmount, 0, ',', '.') . ' dilepaskan ke dompet Receiver.';
            } else { // refund_partial
                if ($refundAmount === null || $refundAmount <= 0 || $refundAmount >= $diff) {
                    throw new DomainException('Nominal refund parsial harus bernilai antara Rp 1 dan Rp ' . number_format($diff - 1, 0, ',', '.'));
                }

                $actualRefund = $refundAmount;
                $releaseAmount = $diff - $actualRefund;

                // Kredit sebagian ke payer
                /** @var User $payer */
                $payer = User::findOrFail($lockedOffer->payer_id);
                $payer->getOrCreateWallet()->credit($actualRefund, 'escrow_refund_partial', $lockedOffer);

                // Kredit sisa ke receiver
                /** @var User $receiver */
                $receiver = User::findOrFail($lockedOffer->receiver_id);
                $receiver->getOrCreateWallet()->credit($releaseAmount, 'escrow_release_partial', $lockedOffer);

                // Transisi escrow menjadi COMPLETED
                $this->stateMachine->transition($lockedOffer, EscrowStatus::COMPLETED, [
                    'released_at' => now(),
                ]);

                $lockedOffer->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                Item::where('id', $lockedOffer->target_item_id)->update(['status' => 'traded']);
                $offererItemIds = $lockedOffer->offererItems()->pluck('items.id')->toArray();
                if (empty($offererItemIds) && $lockedOffer->offerer_item_id) {
                    $offererItemIds = [$lockedOffer->offerer_item_id];
                }
                if (!empty($offererItemIds)) {
                    Item::whereIn('id', $offererItemIds)->update(['status' => 'traded']);
                }

                $decisionText = 'Mediasi parsial: Rp ' . number_format($actualRefund, 0, ',', '.') . ' ke Payer, Rp ' . number_format($releaseAmount, 0, ',', '.') . ' ke Receiver.';
            }

            // Simpan status dispute
            $lockedDispute->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'refund_amount' => $actualRefund,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'admin_note' => $adminNote,
            ]);

            // Catatan chat sistem
            Chat::create([
                'offer_id' => $lockedOffer->id,
                'sender_id' => $admin->id,
                'message' => "Sengketa resmi diselesaikan oleh Admin ({$admin->name}). Keputusan: {$decisionText}." . ($adminNote ? " Catatan: {$adminNote}" : ''),
                'type' => 'system',
            ]);

            return $lockedDispute->fresh(['offer', 'reporter', 'resolver']);
        });
    }
}
