<div>
    <!-- Page Header & Stats -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Aduan & Laporan Pengguna</h1>
            <p class="text-sm text-gray-500 mt-1">Tinjau keluhan dan ambil tindakan langsung terhadap pelanggaran barang atau pengguna.</p>
        </div>
        <div class="flex gap-3">
            <div class="bg-amber-50 border border-amber-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="text-xs font-medium text-amber-800">Menunggu: <strong>{{ $pendingCount }}</strong></span>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-medium text-emerald-800">Ditangani: <strong>{{ $resolvedCount }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-80">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-9 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" placeholder="Cari pelapor, terlapor, keluhan...">
            </div>
        </div>

        <div class="flex gap-2 w-full md:w-auto">
            <button wire:click="$set('statusFilter', 'all')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === 'all' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Semua
            </button>
            <button wire:click="$set('statusFilter', 'pending')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === 'pending' ? 'bg-amber-500 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Menunggu ({{ $pendingCount }})
            </button>
            <button wire:click="$set('statusFilter', 'resolved')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === 'resolved' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Selesai ({{ $resolvedCount }})
            </button>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">Pelapor</th>
                        <th class="px-5 py-3">Objek / Terlapor</th>
                        <th class="px-5 py-3">Alasan & Keluhan</th>
                        <th class="px-5 py-3">Bukti</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reports as $report)
                    <tr class="bg-white hover:bg-gray-50 transition-colors">
                        <!-- Pelapor -->
                        <td class="px-5 py-4">
                            <div class="font-medium text-gray-900">{{ $report->reporter?->name ?? 'User Dihapus' }}</div>
                            <div class="text-xs text-gray-400">{{ $report->reporter?->email ?? '-' }}</div>
                        </td>

                        <!-- Objek / Terlapor -->
                        <td class="px-5 py-4">
                            @if($report->reportable_type === 'App\Models\Item')
                                @if($report->reportable)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">BARANG</span>
                                        <span class="font-medium text-gray-900 truncate max-w-[150px]">{{ $report->reportable->title }}</span>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-0.5">Pemilik: {{ $report->reportedUser?->name ?? 'Unknown' }}</div>
                                @else
                                    <span class="text-xs text-red-500 italic">Barang telah dihapus</span>
                                @endif
                            @elseif($report->reportable_type === 'App\Models\User')
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800">USER</span>
                                    <span class="font-medium text-gray-900">{{ $report->reportedUser?->name ?? 'User' }}</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ $report->reportedUser?->email ?? '-' }}</div>
                            @endif
                        </td>

                        <!-- Alasan & Keluhan -->
                        <td class="px-5 py-4 max-w-xs">
                            <div class="mb-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                    {{ ucwords(str_replace('_', ' ', $report->reason)) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 line-clamp-2" title="{{ $report->description }}">{{ $report->description }}</p>
                        </td>

                        <!-- Bukti Foto -->
                        <td class="px-5 py-4">
                            @if($report->evidence_image)
                                <button wire:click="viewReport({{ $report->id }})" class="group relative block w-10 h-10 rounded-lg overflow-hidden border border-gray-200 hover:opacity-90">
                                    <img src="{{ Storage::url($report->evidence_image) }}" class="w-full h-full object-cover">
                                </button>
                            @else
                                <span class="text-xs text-gray-400 italic">Tanpa foto</span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="px-5 py-4">
                            @if($report->status === 'pending')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                    Menunggu
                                </span>
                            @else
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        Selesai
                                    </span>
                                    @if($report->action_taken)
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            Aksi: {{ str_replace('_', ' ', $report->action_taken) }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>

                        <!-- Tanggal -->
                        <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">
                            {{ $report->created_at->format('d M Y, H:i') }}
                        </td>

                        <!-- Tindakan -->
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                <button wire:click="viewReport({{ $report->id }})" class="p-1.5 text-gray-600 hover:text-emerald-700 bg-gray-100 hover:bg-emerald-50 rounded-lg transition-colors" title="Lihat Rincian">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>

                                @if($report->status === 'pending')
                                    @if($report->reportable_type === 'App\Models\Item' && $report->reportable)
                                        <button wire:click="takedownItem({{ $report->id }})" wire:confirm="Nonaktifkan barang ini dari etalase?" class="px-2 py-1 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors" title="Takedown Barang">
                                            Takedown
                                        </button>
                                    @endif

                                    <button wire:click="dismissReport({{ $report->id }})" wire:confirm="Abaikan laporan ini?" class="px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors" title="Abaikan / Tolak">
                                        Abaikan
                                    </button>
                                @endif

                                <button wire:click="deleteReport({{ $report->id }})" wire:confirm="Hapus data laporan ini?" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg" title="Hapus Laporan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">📋</div>
                            <div class="text-base font-semibold text-gray-600">Tidak ada aduan / laporan</div>
                            <p class="text-xs text-gray-400 mt-1">Belum ada laporan dari pengguna yang cocok dengan kriteria filter.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
        <div class="p-4 border-t border-gray-100">
            {{ $reports->links() }}
        </div>
        @endif
    </div>

    <!-- Detail & Evidence Modal -->
    @if($selectedReport)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                <h3 class="text-lg font-bold text-gray-900">Rincian Laporan #{{ $selectedReport->id }}</h3>
                <button wire:click="closeReportModal" class="text-gray-400 hover:text-gray-600 rounded-full p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="space-y-4">
                <!-- Info cards -->
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="p-3 bg-gray-50 rounded-xl">
                        <span class="text-gray-400 block mb-0.5">Pelapor:</span>
                        <strong class="text-gray-900 block text-sm">{{ $selectedReport->reporter?->name ?? 'User Dihapus' }}</strong>
                        <span class="text-gray-500">{{ $selectedReport->reporter?->email ?? '-' }}</span>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl">
                        <span class="text-gray-400 block mb-0.5">Pihak Terlapor:</span>
                        <strong class="text-gray-900 block text-sm">{{ $selectedReport->reportedUser?->name ?? 'Terlapor' }}</strong>
                        <span class="text-gray-500">{{ $selectedReport->reportedUser?->email ?? '-' }}</span>
                    </div>
                </div>

                @if($selectedReport->reportable_type === 'App\Models\Item' && $selectedReport->reportable)
                <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs">
                    <span class="text-blue-600 font-semibold block mb-0.5">Barang yang Dilaporkan:</span>
                    <strong class="text-blue-900 text-sm">{{ $selectedReport->reportable->title }}</strong>
                    <div class="text-blue-700 mt-1">Status saat ini: <span class="font-bold uppercase">{{ $selectedReport->reportable->status }}</span></div>
                </div>
                @endif

                <!-- Reason & Description -->
                <div>
                    <label class="text-xs font-semibold text-gray-500 block mb-1">Alasan Laporan:</label>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-900">
                        {{ ucwords(str_replace('_', ' ', $selectedReport->reason)) }}
                    </span>
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-500 block mb-1">Keterangan / Keluhan:</label>
                    <div class="p-3 bg-gray-50 rounded-xl text-sm text-gray-800 whitespace-pre-line border border-gray-100">
                        {{ $selectedReport->description }}
                    </div>
                </div>

                <!-- Evidence Photo -->
                @if($selectedReport->evidence_image)
                <div>
                    <label class="text-xs font-semibold text-gray-500 block mb-1">Bukti Foto / Tangkapan Layar:</label>
                    <div class="rounded-xl overflow-hidden border border-gray-200 max-h-64 bg-gray-100 flex items-center justify-center">
                        <img src="{{ Storage::url($selectedReport->evidence_image) }}" class="max-h-64 w-auto object-contain">
                    </div>
                </div>
                @endif

                <!-- Status & Resolver info -->
                <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <div>Status: <strong class="uppercase text-gray-800">{{ $selectedReport->status }}</strong></div>
                    @if($selectedReport->resolved_at)
                    <div>Ditangani: {{ $selectedReport->resolved_at->format('d M Y, H:i') }} ({{ $selectedReport->resolver?->name ?? 'Admin' }})</div>
                    @endif
                </div>

                <!-- Action Buttons in Modal -->
                @if($selectedReport->status === 'pending')
                <div class="pt-3 flex gap-2 justify-end border-t border-gray-100 flex-wrap">
                    @if($selectedReport->reportable_type === 'App\Models\Item' && $selectedReport->reportable)
                        <button wire:click="takedownItem({{ $selectedReport->id }})" wire:confirm="Takedown barang ini dari etalase?" class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-sm transition-colors">
                            ⚡ Takedown Barang
                        </button>
                        <button wire:click="deleteItem({{ $selectedReport->id }})" wire:confirm="Hapus barang ini secara permanen?" class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-sm transition-colors">
                            🗑 Hapus Barang
                        </button>
                    @endif

                    @if($selectedReport->reported_user_id)
                        <button wire:click="banUser({{ $selectedReport->id }})" wire:confirm="Cabut sesi login dan moderasi barang milik pengguna ini?" class="px-4 py-2 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-xl shadow-sm transition-colors">
                            ⛔ Tindak Akun Terlapor
                        </button>
                    @endif

                    <button wire:click="dismissReport({{ $selectedReport->id }})" wire:confirm="Abaikan dan tandai selesai?" class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-xl transition-colors">
                        Abaikan Laporan
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
