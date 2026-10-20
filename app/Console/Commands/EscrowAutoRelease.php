<?php

namespace App\Console\Commands;

use App\Enums\EscrowStatus;
use App\Models\Chat;
use App\Models\Offer;
use App\Services\EscrowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EscrowAutoRelease extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'escrow:auto-release';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis melepaskan dana escrow untuk transaksi yang telah melewati batas waktu konfirmasi (confirm_deadline_at)';

    /**
     * Execute the console command.
     */
    public function handle(EscrowService $escrowService): int
    {
        $this->info('Memulai pengecekan transaksi escrow yang melewati batas waktu konfirmasi...');

        $expiredOffers = Offer::where('payment_method', 'gateway')
            ->where('escrow_status', EscrowStatus::WAITING_CONFIRMATION)
            ->where('confirm_deadline_at', '<=', now())
            ->whereDoesntHave('disputes', fn ($q) => $q->where('status', 'open'))
            ->get();

        $releasedCount = 0;
        $reviewNeededCount = 0;

        foreach ($expiredOffers as $offer) {
            $hasAtLeastOneConfirmation = ($offer->payer_confirmed_at !== null || $offer->receiver_confirmed_at !== null);

            if ($hasAtLeastOneConfirmation) {
                try {
                    $escrowService->releaseFunds($offer, 'auto_release');

                    Chat::create([
                        'offer_id' => $offer->id,
                        'sender_id' => $offer->receiver_id,
                        'message' => 'Batas waktu konfirmasi serah terima (48 jam) telah berakhir. Karena sudah ada konfirmasi dari salah satu pihak dan tidak ada sengketa, dana secara otomatis dilepaskan ke dompet penerima (Auto-Release).',
                        'type' => 'system',
                    ]);

                    $this->info("Transaksi #{$offer->id}: Dana berhasil dilepas otomatis.");
                    $releasedCount++;
                } catch (\Throwable $e) {
                    $this->error("Transaksi #{$offer->id} gagal dilepas: " . $e->getMessage());
                    Log::error("EscrowAutoRelease failed for offer #{$offer->id}: " . $e->getMessage());
                }
            } else {
                // Tidak ada pihak yang konfirmasi sama sekali -> tandai butuh review admin
                Chat::create([
                    'offer_id' => $offer->id,
                    'sender_id' => $offer->receiver_id,
                    'message' => 'Batas waktu konfirmasi telah habis dan kedua belah pihak belum mengonfirmasi serah terima barang. Transaksi ini ditandai untuk peninjauan khusus oleh Admin.',
                    'type' => 'system',
                ]);

                $this->warn("Transaksi #{$offer->id}: Kedua pihak belum konfirmasi, ditandai butuh review admin (tidak dilepas otomatis).");
                $reviewNeededCount++;
            }
        }

        $this->info("Selesai. {$releasedCount} transaksi dirilis otomatis, {$reviewNeededCount} transaksi butuh review admin.");

        return Command::SUCCESS;
    }
}
