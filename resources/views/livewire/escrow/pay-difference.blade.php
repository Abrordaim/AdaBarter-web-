<div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
    @if ($errorMessage)
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-300 text-sm">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($offer)
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Pembayaran Selisih Barter (Escrow)</h3>
                <p class="text-sm text-slate-500">Transaksi #{{ $offer->id }} &bull; Metode: Rekening Bersama (Midtrans)</p>
            </div>
            <span class="px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                {{ $offer->escrow_status?->label() ?? 'Menunggu Pembayaran' }}
            </span>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl mb-4 border border-slate-100 dark:border-slate-700/60">
            <div class="flex justify-between items-center mb-1">
                <span class="text-sm text-slate-600 dark:text-slate-400">Total Selisih Wajib Bayar:</span>
                <span class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format($offer->price_difference, 0, ',', '.') }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Dana akan ditampung aman di rekening bersama AdaBarter sampai kedua belah pihak menyelesaikan serah terima fisik barang.
            </p>
        </div>

        @if (auth()->id() === $offer->payer_id && ($offer->escrow_status === null || $offer->escrow_status === \App\Enums\EscrowStatus::WAITING_PAYMENT))
            <button
                wire:click="pay"
                wire:loading.attr="disabled"
                type="button"
                class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition duration-150 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
                <span wire:loading.remove>💳 Bayar Selisih Sekarang</span>
                <span wire:loading>Memuat pembayaran Snap...</span>
            </button>
        @elseif (auth()->id() === $offer->receiver_id)
            <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-sm">
                ℹ️ Anda adalah pihak penerima selisih. Menunggu pembayaran diselesaikan oleh pihak pembayar ({{ $offer->payer?->name }}).
            </div>
        @else
            <div class="text-sm text-slate-500">
                Status pembayaran: <strong>{{ $offer->escrow_status?->label() }}</strong>
            </div>
        @endif
    @endif

    {{-- Midtrans Snap Script --}}
    <script src="{{ $snapJsUrl }}" data-client-key="{{ $clientKey }}"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('open-midtrans-snap', (event) => {
                const data = Array.isArray(event) ? event[0] : event;
                const snapToken = data.token;

                if (window.snap && snapToken) {
                    window.snap.pay(snapToken, {
                        onSuccess: function(result) {
                            alert('Pembayaran berhasil! Halaman akan dimuat ulang.');
                            window.location.reload();
                        },
                        onPending: function(result) {
                            alert('Pembayaran sedang menunggu penyelesaian.');
                            window.location.reload();
                        },
                        onError: function(result) {
                            alert('Pembayaran gagal atau dibatalkan.');
                        },
                        onClose: function() {
                            // User menutup popup Snap tanpa menyelesaikan
                        }
                    });
                } else if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            });
        });
    </script>
</div>
