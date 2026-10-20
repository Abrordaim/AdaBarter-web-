<?php

namespace App\Livewire\Escrow;

use App\Models\Offer;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PayDifference extends Component
{
    public int $offerId;
    public ?Offer $offer = null;
    public bool $loading = false;
    public ?string $errorMessage = null;

    public function mount(int $offerId): void
    {
        $this->offerId = $offerId;
        $this->loadOffer();
    }

    public function loadOffer(): void
    {
        $this->offer = Offer::with(['offerer', 'targetOwner', 'offererItem', 'targetItem'])->find($this->offerId);
    }

    public function pay(PaymentService $paymentService): void
    {
        $this->errorMessage = null;
        $user = Auth::user();

        if (!$user) {
            $this->errorMessage = 'Silakan masuk ke akun Anda terlebih dahulu.';
            return;
        }

        if (!$this->offer) {
            $this->errorMessage = 'Data transaksi tidak ditemukan.';
            return;
        }

        try {
            $result = $paymentService->createSnapToken($user, $this->offer);

            $this->dispatch('open-midtrans-snap', [
                'token' => $result['token'],
                'redirect_url' => $result['redirect_url'],
            ]);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $clientKey = config('services.midtrans.client_key');
        $isProduction = config('services.midtrans.is_production');
        $snapJsUrl = $isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';

        return view('livewire.escrow.pay-difference', [
            'clientKey' => $clientKey,
            'snapJsUrl' => $snapJsUrl,
        ]);
    }
}
