<div>
    <!-- Page Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Penarikan Dana Pengguna</h1>
            <p class="text-sm text-gray-500 mt-1">Verifikasi dan proses transfer bank permohonan pencairan saldo dompet pengguna.</p>
        </div>
        <div class="flex gap-3">
            <div class="bg-amber-50 border border-amber-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 {{ $pendingCount > 0 ? 'animate-pulse' : '' }}"></span>
                <span class="text-xs font-medium text-amber-800">Pending: <strong>{{ $pendingCount }}</strong></span>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="text-xs font-medium text-emerald-800">Total Ditahan:
                    <strong>Rp {{ number_format($totalAmountPending, 0, ',', '.') }}</strong>
                </span>
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
            <button wire:click="$set('successMessage', null)" class="text-emerald-500 hover:text-emerald-700 text-lg">&times;</button>
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-80">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" placeholder="Cari nama, bank, rekening...">
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @foreach(['all' => 'Semua', 'pending' => 'Pending', 'paid' => 'Lunas', 'rejected' => 'Ditolak'] as $val => $label)
                <button wire:click="$set('statusFilter', '{{ $val }}')"
                    class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === $val ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">Pengguna</th>
                        <th class="px-5 py-3">Rekening Tujuan</th>
                        <th class="px-5 py-3">Nominal</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tanggal Ajuan</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($withdrawals as $w)
                        <tr class="bg-white hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900">{{ $w->user?->name }}</div>
                                <div class="text-xs text-gray-400">{{ $w->user?->email }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-800">{{ $w->bank_name }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $w->account_number }}</div>
                                <div class="text-xs text-gray-500">a/n {{ $w->account_name }}</div>
                            </td>
                            <td class="px-5 py-4 font-bold text-gray-900">
                                Rp {{ number_format($w->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4">
                                @if($w->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Menunggu</span>
                                @elseif($w->status === 'paid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Lunas</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Ditolak</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">
                                {{ $w->created_at?->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button wire:click="viewWithdrawal({{ $w->id }})"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors">
                                    {{ $w->isPending() ? 'Proses' : 'Detail' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">Tidak ada data penarikan ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $withdrawals->links() }}
        </div>
    </div>

    <!-- Modal -->
    @if($selectedWithdrawal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/70 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden border border-gray-100">
                <!-- Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Permohonan Penarikan #{{ $selectedWithdrawal->id }}</h3>
                        <p class="text-xs text-gray-500">{{ $selectedWithdrawal->created_at?->translatedFormat('d F Y, H:i') }}</p>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-5">
                    <!-- Info Pengguna -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-xl p-3">
                            <span class="text-xs text-gray-500 block mb-1">Pengguna</span>
                            <span class="font-semibold text-gray-900">{{ $selectedWithdrawal->user?->name }}</span>
                            <span class="text-xs text-gray-500 block">{{ $selectedWithdrawal->user?->email }}</span>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-3">
                            <span class="text-xs text-gray-500 block mb-1">Nominal Penarikan</span>
                            <span class="font-bold text-xl text-emerald-700">Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Info Rekening -->
                    <div class="bg-gray-50 rounded-xl p-4 text-sm space-y-2">
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Rekening Tujuan Transfer</h4>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Bank</span>
                            <span class="font-semibold text-gray-900">{{ $selectedWithdrawal->bank_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">No. Rekening</span>
                            <span class="font-mono font-bold text-gray-900 tracking-widest">{{ $selectedWithdrawal->account_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Atas Nama</span>
                            <span class="font-semibold text-gray-900">{{ $selectedWithdrawal->account_name }}</span>
                        </div>
                    </div>

                    @if($selectedWithdrawal->isPending())
                        <!-- Form Penolakan -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Penolakan (wajib diisi jika menolak):</label>
                            <textarea wire:model="rejectionReason" rows="2" placeholder="Contoh: Nomor rekening tidak valid, nama tidak sesuai KTP..." class="w-full text-sm border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        @if($errorMessage)
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">{{ $errorMessage }}</div>
                        @endif
                    @else
                        <!-- Status Resolved -->
                        <div class="p-4 {{ $selectedWithdrawal->status === 'paid' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200' }} border rounded-xl text-sm space-y-1">
                            <div class="font-semibold {{ $selectedWithdrawal->status === 'paid' ? 'text-emerald-800' : 'text-red-800' }}">
                                {{ $selectedWithdrawal->status === 'paid' ? '✓ Telah Ditransfer' : '✗ Ditolak' }}
                            </div>
                            <div class="text-xs text-gray-600">
                                Diproses oleh: <strong>{{ $selectedWithdrawal->processor?->name ?? 'Admin' }}</strong>
                                pada {{ $selectedWithdrawal->processed_at?->translatedFormat('d F Y, H:i') }}
                            </div>
                            @if($selectedWithdrawal->rejection_reason)
                                <div class="text-xs text-red-700 pt-1">Alasan: {{ $selectedWithdrawal->rejection_reason }}</div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button wire:click="closeModal" class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Tutup
                    </button>
                    @if($selectedWithdrawal->isPending())
                        <button wire:click="reject"
                            wire:confirm="Tolak penarikan ini dan kembalikan saldo ke dompet pengguna?"
                            class="px-4 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm">
                            Tolak & Kembalikan Saldo
                        </button>
                        <button wire:click="approve"
                            wire:confirm="Tandai penarikan ini sebagai LUNAS? Pastikan transfer bank sudah dilakukan."
                            class="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-sm">
                            Tandai Lunas (Sudah Transfer)
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
