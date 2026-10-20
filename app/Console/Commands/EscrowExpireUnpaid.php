<?php

namespace App\Console\Commands;

use App\Enums\EscrowStatus;
use App\Models\Offer;
use App\Services\EscrowStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EscrowExpireUnpaid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'escrow:expire-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menandai EXPIRED untuk transaksi escrow yang belum dibayar dalam batas waktu 24 jam';

    /**
     * Execute the console command.
     */
    public function handle(EscrowStateMachine $stateMachine): int
    {
        $this->info('Memeriksa transaksi escrow yang belum dibayar melewati batas 24 jam...');

        $unpaidOffers = Offer::where('payment_method', 'gateway')
            ->where('escrow_status', EscrowStatus::WAITING_PAYMENT)
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        $expiredCount = 0;

        foreach ($unpaidOffers as $offer) {
            try {
                $stateMachine->transition($offer, EscrowStatus::EXPIRED);
                $this->info("Transaksi #{$offer->id}: Ditandai EXPIRED.");
                $expiredCount++;
            } catch (\Throwable $e) {
                $this->error("Gagal mengubah transaksi #{$offer->id} ke EXPIRED: " . $e->getMessage());
                Log::error("EscrowExpireUnpaid failed for offer #{$offer->id}: " . $e->getMessage());
            }
        }

        $this->info("Selesai. {$expiredCount} transaksi kadaluwarsa.");

        return Command::SUCCESS;
    }
}
