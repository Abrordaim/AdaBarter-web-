<div>
    <!-- Page Header & Stats -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Sengketa Transaksi Escrow</h1>
            <p class="text-sm text-gray-500 mt-1">Mediasi dan putuskan pengembalian atau pelepasan dana penampungan rekening bersama (escrow).</p>
        </div>
        <div class="flex gap-3">
            <div class="bg-amber-50 border border-amber-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 {{ $openCount > 0 ? 'animate-pulse' : '' }}"></span>
                <span class="text-xs font-medium text-amber-800">Perlu Mediasi: <strong>{{ $openCount }}</strong></span>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-medium text-emerald-800">Diselesaikan: <strong>{{ $resolvedCount }}</strong></span>
            </div>
            <div class="bg-gray-50 border border-gray-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="text-xs font-medium text-gray-600">Total: <strong>{{ $totalCount }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Notification Alerts -->
    @if($successMessage)
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ $successMessage }}</span>
            </div>
            <button wire:click="$set('successMessage', null)" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-red-500 hover:text-red-700">&times;</button>
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-80">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-9 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" placeholder="Cari alasan, pelapor, ID barter...">
            </div>
        </div>

        <div class="flex gap-2 w-full md:w-auto">
            <button wire:click="$set('statusFilter', 'all')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === 'all' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Semua
            </button>
            <button wire:click="$set('statusFilter', 'open')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === 'open' ? 'bg-amber-500 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Perlu Mediasi ({{ $openCount }})
            </button>
            <button wire:click="$set('statusFilter', 'resolved')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === 'resolved' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Selesai ({{ $resolvedCount }})
            </button>
        </div>
    </div>

    <!-- Disputes Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">ID / Tawaran</th>
                        <th class="px-5 py-3">Pelapor</th>
                        <th class="px-5 py-3">Pihak Transaksi</th>
                        <th class="px-5 py-3">Selisih Dana</th>
                        <th class="px-5 py-3">Alasan Sengketa</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($disputes as $dispute)
                        <tr class="bg-white hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-bold text-gray-900">#DISP-{{ $dispute->id }}</div>
                                <div class="text-xs text-emerald-600 font-medium">Barter #{{ $dispute->offer_id }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900">{{ $dispute->reporter?->name ?? 'User #' . $dispute->reported_by }}</div>
                                <div class="text-xs text-gray-400">{{ $dispute->reporter?->email ?? '-' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs">
                                    <span class="text-gray-500">Payer:</span> <strong>{{ $dispute->offer?->payer?->name ?? '-' }}</strong>
                                </div>
                                <div class="text-xs mt-0.5">
                                    <span class="text-gray-500">Receiver:</span> <strong>{{ $dispute->offer?->receiver?->name ?? '-' }}</strong>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-semibold text-gray-900">
                                Rp {{ number_format($dispute->offer?->price_difference ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900 truncate max-w-[200px]">{{ $dispute->reason }}</div>
                                <div class="text-xs text-gray-400 truncate max-w-[200px]">{{ $dispute->description }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if($dispute->isOpen())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                        Perlu Mediasi
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        {{ $dispute->resolution === 'refund_full' ? 'Refund Full' : ($dispute->resolution === 'release' ? 'Released' : 'Split/Parsial') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">
                                {{ $dispute->created_at?->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button wire:click="viewDispute({{ $dispute->id }})" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors">
                                    {{ $dispute->isOpen() ? 'Mediasi' : 'Detail' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-gray-400">
                                Tidak ada sengketa transaksi yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $disputes->links() }}
        </div>
    </div>

    <!-- Modal Mediasi & Detail Sengketa -->
    @if($selectedDispute)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl overflow-hidden border border-gray-100">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            Sengketa Transaksi #DISP-{{ $selectedDispute->id }}
                        </h3>
                        <p class="text-xs text-gray-500">Terkait Tawaran Barter #{{ $selectedDispute->offer_id }} &bull; Escrow: {{ $selectedDispute->offer?->escrow_status?->value ?? '-' }}</p>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                    <!-- Ringkasan Finansial & Pihak -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 p-4 bg-gray-50 rounded-xl text-xs">
                        <div>
                            <span class="text-gray-500 block">Total Selisih Dana</span>
                            <span class="font-bold text-sm text-emerald-700">Rp {{ number_format($selectedDispute->offer?->price_difference ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Pembayar (Payer)</span>
                            <span class="font-semibold text-gray-800">{{ $selectedDispute->offer?->payer?->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Penerima (Receiver)</span>
                            <span class="font-semibold text-gray-800">{{ $selectedDispute->offer?->receiver?->name ?? '-' }}</span>
                        </div>
                    </div>

                    <!-- Detail Komplain -->
                    <div class="border border-gray-100 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Laporan dari: {{ $selectedDispute->reporter?->name }}</span>
                            <span class="text-xs text-gray-400">{{ $selectedDispute->created_at?->translatedFormat('d F Y, H:i') }}</span>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-1">{{ $selectedDispute->reason }}</h4>
                        <p class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 p-3 rounded-lg border border-gray-100">{{ $selectedDispute->description }}</p>

                        <!-- Bukti Foto -->
                        @if(!empty($selectedDispute->evidence_paths))
                            <div class="mt-4">
                                <span class="text-xs font-semibold text-gray-600 block mb-2">Lampiran Bukti Foto:</span>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    @foreach($selectedDispute->evidence_paths as $path)
                                        <a href="{{ asset('storage/' . $path) }}" target="_blank" class="block border border-gray-200 rounded-lg overflow-hidden hover:opacity-90 transition-opacity">
                                            <img src="{{ asset('storage/' . $path) }}" alt="Bukti Sengketa" class="w-full h-28 object-cover">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Panel Keputusan Admin -->
                    @if($selectedDispute->isOpen())
                        <div class="border-2 border-emerald-100 bg-emerald-50/30 rounded-xl p-4 space-y-4">
                            <h4 class="font-bold text-emerald-900 text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Keputusan Mediasi Admin
                            </h4>

                            <div class="space-y-2">
                                <label class="flex items-start gap-3 p-3 bg-white rounded-lg border border-gray-200 cursor-pointer hover:border-emerald-400">
                                    <input type="radio" wire:model.live="resolutionType" value="refund_full" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-900">Refund Penuh ke Pembayar (Payer)</span>
                                        <span class="block text-xs text-gray-500">Kembalikan 100% selisih dana (Rp {{ number_format($selectedDispute->offer?->price_difference ?? 0, 0, ',', '.') }}) ke dompet Payer. Transaksi barter dibatalkan, barang dikembalikan ke status aktif.</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 bg-white rounded-lg border border-gray-200 cursor-pointer hover:border-emerald-400">
                                    <input type="radio" wire:model.live="resolutionType" value="release" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-900">Lepaskan ke Penerima (Receiver)</span>
                                        <span class="block text-xs text-gray-500">Komplain ditolak atau diselesaikan. Dana selisih diteruskan ke dompet Receiver. Transaksi barter ditandai selesai (completed), barang menjadi traded.</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 bg-white rounded-lg border border-gray-200 cursor-pointer hover:border-emerald-400">
                                    <input type="radio" wire:model.live="resolutionType" value="refund_partial" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                    <div class="flex-1">
                                        <span class="block text-sm font-semibold text-gray-900">Mediasi Parsial (Split Dana)</span>
                                        <span class="block text-xs text-gray-500">Sebagian dana dikembalikan ke Payer, dan sisanya dicairkan ke Receiver.</span>
                                    </div>
                                </label>
                            </div>

                            @if($resolutionType === 'refund_partial')
                                <div class="p-3 bg-white rounded-lg border border-gray-200 space-y-2">
                                    <label class="block text-xs font-semibold text-gray-700">Nominal Pengembalian ke Payer (Rupiah):</label>
                                    <input type="number" wire:model.live="refundAmount" min="1" max="{{ ($selectedDispute->offer?->price_difference ?? 1) - 1 }}" placeholder="Contoh: 50000" class="w-full text-sm border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                    @if($refundAmount && $refundAmount > 0 && $refundAmount < ($selectedDispute->offer?->price_difference ?? 0))
                                        <div class="text-xs text-gray-600 flex justify-between pt-1">
                                            <span>Ke Payer: <strong>Rp {{ number_format($refundAmount, 0, ',', '.') }}</strong></span>
                                            <span>Ke Receiver: <strong>Rp {{ number_format(($selectedDispute->offer?->price_difference ?? 0) - $refundAmount, 0, ',', '.') }}</strong></span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Pertimbangan Admin (Wajib Transparan):</label>
                                <textarea wire:model="adminNote" rows="3" placeholder="Tuliskan alasan keputusan mediasi ini..." class="w-full text-sm border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                            </div>
                        </div>
                    @else
                        <!-- Tampilan Hasil Mediasi -->
                        <div class="border border-emerald-200 bg-emerald-50/50 rounded-xl p-4 text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-emerald-800 uppercase tracking-wider">Hasil Keputusan Mediasi</span>
                                <span class="text-gray-500">{{ $selectedDispute->resolved_at?->translatedFormat('d F Y, H:i') }}</span>
                            </div>
                            <div class="text-sm font-semibold text-gray-800">
                                Keputusan: 
                                <span class="text-emerald-700">
                                    {{ $selectedDispute->resolution === 'refund_full' ? 'Refund Penuh ke Pembayar' : ($selectedDispute->resolution === 'release' ? 'Dana Dilepaskan ke Penerima' : 'Mediasi Parsial') }}
                                </span>
                            </div>
                            @if($selectedDispute->refund_amount)
                                <div>Nominal Refund ke Payer: <strong>Rp {{ number_format($selectedDispute->refund_amount, 0, ',', '.') }}</strong></div>
                            @endif
                            <div>Diputuskan oleh: <strong>{{ $selectedDispute->resolver?->name ?? 'Admin' }}</strong></div>
                            @if($selectedDispute->admin_note)
                                <div class="pt-2 border-t border-emerald-200/60 text-gray-700">
                                    <span class="font-semibold block text-gray-600">Catatan Admin:</span>
                                    {{ $selectedDispute->admin_note }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button wire:click="closeModal" class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Tutup
                    </button>
                    @if($selectedDispute->isOpen())
                        <button wire:click="resolve" wire:confirm="Apakah Anda yakin ingin mengeksekusi keputusan sengketa ini? Tindakan ini tidak dapat dibatalkan." class="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-sm transition-colors">
                            Terapkan Keputusan Mediasi
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
