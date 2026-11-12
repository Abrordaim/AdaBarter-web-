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
            <div class="bg-purple-50 border border-purple-200 px-4 py-2 rounded-xl flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-purple-500"></span>
                <span class="text-xs font-medium text-purple-800">Banding: <strong>{{ $pendingAppealsCount }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="flex gap-2 border-b border-gray-200 mb-6">
        <button wire:click="setTab('reports')" class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors {{ $activeTab === 'reports' ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50 rounded-t-lg' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            📋 Laporan & Aduan ({{ $pendingCount }})
        </button>
        <button wire:click="setTab('appeals')" class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-1.5 {{ $activeTab === 'appeals' ? 'border-purple-600 text-purple-700 bg-purple-50/50 rounded-t-lg' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            ⚖️ Banding Pengguna
            @if($pendingAppealsCount > 0)
                <span class="px-2 py-0.5 text-[10px] font-bold text-white bg-red-500 rounded-full animate-pulse">{{ $pendingAppealsCount }}</span>
            @endif
        </button>
    </div>

    @if($activeTab === 'reports')
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

                                    @if($report->reportedUser)
                                        <button wire:click="openPenaltyModal({{ $report->id }})" class="px-2 py-1 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors" title="Beri Poin Penalti">
                                            + Poin
                                        </button>
                                    @endif

                                    @if($report->reportable_type === 'App\Models\User' && $report->reportedUser)
                                        @if($report->reportedUser->is_banned)
                                            <span class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded-lg">✓ Sudah Di-ban</span>
                                        @else
                                            <button wire:click="banUser({{ $report->id }})" wire:confirm="Ban akun '{{ $report->reportedUser->name }}'? Akun akan diblokir dan tidak bisa login." class="px-2 py-1 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                                Ban Akun
                                            </button>
                                        @endif
                                    @endif

                                    <button wire:click="dismissReport({{ $report->id }})" wire:confirm="Abaikan laporan ini?" class="px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors" title="Abaikan / Tolak">
                                        Abaikan
                                    </button>
                                @else
                                    {{-- Resolved: show unban button if the user is still banned --}}
                                    @if($report->reportedUser && $report->reportedUser->is_banned)
                                        <button wire:click="unbanUser({{ $report->reportedUser->id }})" wire:confirm="Unban akun '{{ $report->reportedUser->name }}'? Mereka akan bisa login kembali." class="px-2 py-1 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors">
                                            Unban
                                        </button>
                                    @endif
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
    @endif

    @if($activeTab === 'appeals')
    <!-- Appeals Search & Info -->
    <div class="mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-80">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-9 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" placeholder="Cari nama pengguna, alasan banding...">
            </div>
        </div>
        <div class="text-xs text-gray-500">
            Pengguna berhak mengajukan 1 kali banding per sanksi dalam 7 hari setelah poin diberikan.
        </div>
    </div>

    <!-- Appeals Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">Pengguna</th>
                        <th class="px-5 py-3">Sanksi Asal</th>
                        <th class="px-5 py-3">Alasan Sanggahan</th>
                        <th class="px-5 py-3">Bukti</th>
                        <th class="px-5 py-3">Tanggal Pengajuan</th>
                        <th class="px-5 py-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($appeals as $appeal)
                    <tr class="bg-white hover:bg-gray-50 transition-colors">
                        <!-- Pengguna -->
                        <td class="px-5 py-4">
                            <div class="font-medium text-gray-900">{{ $appeal->user?->name ?? 'User Dihapus' }}</div>
                            <div class="text-xs text-gray-400">{{ $appeal->user?->email ?? '-' }}</div>
                            <div class="mt-1 flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                    {{ $appeal->user?->penalty_points ?? 0 }} Poin
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $appeal->user?->account_status === 'banned' ? 'bg-red-100 text-red-800' : ($appeal->user?->account_status === 'restricted' ? 'bg-orange-100 text-orange-800' : 'bg-emerald-100 text-emerald-800') }}">
                                    {{ strtoupper($appeal->user?->account_status ?? 'HEALTHY') }}
                                </span>
                            </div>
                        </td>

                        <!-- Sanksi Asal -->
                        <td class="px-5 py-4 max-w-xs">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                    +{{ $appeal->points }} Poin
                                </span>
                                <span class="text-xs font-semibold text-gray-700">
                                    {{ ucwords(str_replace('_', ' ', $appeal->category)) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 line-clamp-2">{{ $appeal->reason }}</p>
                        </td>

                        <!-- Alasan Sanggahan -->
                        <td class="px-5 py-4 max-w-sm">
                            <p class="text-xs text-gray-800 bg-gray-50 p-2.5 rounded-lg border border-gray-100 line-clamp-3">
                                "{{ $appeal->appeal_reason }}"
                            </p>
                        </td>

                        <!-- Bukti -->
                        <td class="px-5 py-4">
                            @if($appeal->appeal_evidence)
                                <a href="{{ Storage::url($appeal->appeal_evidence) }}" target="_blank" class="block w-12 h-12 rounded-lg overflow-hidden border border-gray-200 hover:opacity-90">
                                    <img src="{{ Storage::url($appeal->appeal_evidence) }}" class="w-full h-full object-cover">
                                </a>
                            @else
                                <span class="text-xs text-gray-400 italic">Tanpa bukti foto</span>
                            @endif
                        </td>

                        <!-- Tanggal Pengajuan -->
                        <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">
                            {{ $appeal->appealed_at?->format('d M Y, H:i') ?? '-' }}
                        </td>

                        <!-- Tindakan -->
                        <td class="px-5 py-4 text-right">
                            <button wire:click="openAppealModal({{ $appeal->id }})" class="px-3 py-1.5 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-lg shadow-sm transition-colors">
                                ⚖️ Tinjau Banding
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">⚖️</div>
                            <div class="text-base font-semibold text-gray-600">Tidak ada pengajuan banding yang menunggu</div>
                            <p class="text-xs text-gray-400 mt-1">Saat ini belum ada pengguna yang mengajukan banding atas sanksi poin penalti.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($appeals->hasPages())
        <div class="p-4 border-t border-gray-100">
            {{ $appeals->links() }}
        </div>
        @endif
    </div>
    @endif

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
                        <button wire:click="openPenaltyModal({{ $selectedReport->id }})" class="px-4 py-2 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-xl shadow-sm transition-colors">
                            + Beri Poin Penalti
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

    <!-- Penalty Award Modal -->
    @if($penaltyModalVisible)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                <h3 class="text-lg font-bold text-gray-900">Beri Poin Penalti Pengguna</h3>
                <button wire:click="closePenaltyModal" class="text-gray-400 hover:text-gray-600 rounded-full p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="space-y-4">
                <!-- User Summary -->
                <div class="p-3 bg-purple-50 rounded-xl border border-purple-100 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-purple-900 text-sm">{{ $penaltyTargetUser?->name }}</div>
                        <div class="text-xs text-purple-700">{{ $penaltyTargetUser?->email }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-purple-600">Poin Saat Ini:</div>
                        <div class="font-bold text-purple-900 text-base">{{ $penaltyTargetUser?->penalty_points ?? 0 }} Poin</div>
                    </div>
                </div>

                <!-- Points Selection -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Jumlah Poin Penalti</label>
                    <select wire:model="penaltyPoints" class="w-full text-sm border-gray-300 rounded-xl focus:ring-purple-500 focus:border-purple-500">
                        <option value="1">1 Poin - Peringatan / Pelanggaran Ringan</option>
                        <option value="2">2 Poin - Pelanggaran Ringan Berulang</option>
                        <option value="3">3 Poin - Pelanggaran Sedang (Tier 1: No Boost, Feed De-ranking)</option>
                        <option value="5">5 Poin - Pelanggaran Serius</option>
                        <option value="8">8 Poin - Pelanggaran Berat (Tier 2: Larangan Posting & Barter Baru)</option>
                        <option value="15">15 Poin - Pelanggaran Fatal (Tier 4: Pemblokiran Akun Permanen)</option>
                    </select>
                </div>

                <!-- Category Selection -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Kategori Pelanggaran</label>
                    <select wire:model="penaltyCategory" class="w-full text-sm border-gray-300 rounded-xl focus:ring-purple-500 focus:border-purple-500">
                        <option value="katalog_menyesatkan">Katalog Menyesatkan / Foto Palsu</option>
                        <option value="spam_chat">Spam Obrolan / Pelecehan / Chat Kasar</option>
                        <option value="barang_terlarang">Barang Ilegal / Terlarang / Berbahaya</option>
                        <option value="batal_sepihak">Pembatalan Sepihak Berulang</option>
                        <option value="penipuan_transaksi">Indikasi Penipuan / Transaksi Palsu</option>
                        <option value="pelanggaran_berat">Pelanggaran Berat Syarat & Ketentuan</option>
                    </select>
                </div>

                <!-- Reason Notes -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Alasan & Catatan Pelanggaran</label>
                    <textarea wire:model="penaltyReason" rows="3" class="w-full text-sm border-gray-300 rounded-xl focus:ring-purple-500 focus:border-purple-500" placeholder="Jelaskan alasan pemberian poin..."></textarea>
                </div>

                <!-- Tier System Guide -->
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-[11px] text-gray-600 space-y-1">
                    <div class="font-semibold text-gray-800">Tingkat Sanksi Otomatis:</div>
                    <div>• <strong>Tier 0 (0-2 Poin)</strong>: Peringatan tertulis tanpa pembatasan.</div>
                    <div>• <strong>Tier 1 (3-5 Poin)</strong>: Larangan Boost & Feed de-ranking (28 hari).</div>
                    <div>• <strong>Tier 2 (6-8 Poin)</strong>: Larangan Posting & Penawaran Baru (28 hari).</div>
                    <div>• <strong>Tier 3 (9-14 Poin)</strong>: Sembunyikan katalog & Larangan Chat Baru.</div>
                    <div>• <strong>Tier 4 (15+ Poin)</strong>: Pemblokiran akun permanen (Banned).</div>
                </div>

                <!-- Actions -->
                <div class="pt-3 flex gap-2 justify-end border-t border-gray-100">
                    <button wire:click="closePenaltyModal" type="button" class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                        Batal
                    </button>
                    <button wire:click="submitAwardPenalty" type="button" class="px-4 py-2 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-xl shadow-sm transition-colors">
                        Konfirmasi & Berikan Poin
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Appeal Review Modal -->
    @if($appealModalVisible && $selectedPenalty)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                <h3 class="text-lg font-bold text-gray-900">Tinjau Pengajuan Banding</h3>
                <button wire:click="closeAppealModal" class="text-gray-400 hover:text-gray-600 rounded-full p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="space-y-4">
                <!-- User & Penalty Info -->
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Pengguna:</span>
                        <strong class="text-gray-900">{{ $selectedPenalty->user?->name }} ({{ $selectedPenalty->user?->email }})</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Sanksi Asal:</span>
                        <span class="font-bold text-purple-700">+{{ $selectedPenalty->points }} Poin ({{ ucwords(str_replace('_', ' ', $selectedPenalty->category)) }})</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Alasan Pelanggaran:</span>
                        <span class="text-gray-800 text-right">{{ $selectedPenalty->reason }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Tanggal Diberikan:</span>
                        <span class="text-gray-800">{{ $selectedPenalty->created_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>

                <!-- User Appeal Statement -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Alasan Sanggahan Pengguna:</label>
                    <div class="p-3 bg-purple-50 rounded-xl border border-purple-100 text-sm text-purple-950 whitespace-pre-line">
                        "{{ $selectedPenalty->appeal_reason }}"
                    </div>
                </div>

                <!-- Evidence Photo if available -->
                @if($selectedPenalty->appeal_evidence)
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Bukti Foto / Tangkapan Layar:</label>
                    <div class="rounded-xl overflow-hidden border border-gray-200 max-h-56 bg-gray-100 flex items-center justify-center">
                        <a href="{{ Storage::url($selectedPenalty->appeal_evidence) }}" target="_blank" class="block w-full text-center">
                            <img src="{{ Storage::url($selectedPenalty->appeal_evidence) }}" class="max-h-56 w-auto mx-auto object-contain">
                        </a>
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Klik gambar untuk melihat resolusi penuh di tab baru.</span>
                </div>
                @endif

                <!-- Admin Resolution Notes -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Catatan Keputusan Admin (Opsional):</label>
                    <input wire:model="appealAdminNotes" type="text" class="w-full text-sm border-gray-300 rounded-xl focus:ring-purple-500 focus:border-purple-500" placeholder="Alasan penerimaan / penolakan banding...">
                </div>

                <!-- Actions -->
                <div class="pt-3 flex gap-2 justify-end border-t border-gray-100">
                    <button wire:click="closeAppealModal" type="button" class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                        Batal
                    </button>
                    <button wire:click="rejectAppeal" wire:confirm="Tolak banding ini? Sanksi dan poin penalti akan tetap berlaku." type="button" class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-sm transition-colors">
                        ✕ Tolak Banding
                    </button>
                    <button wire:click="acceptAppeal" wire:confirm="Setujui banding? Poin penalti akan dicabut dan hak akun dipulihkan secara otomatis." type="button" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-colors">
                        ✓ Setujui Banding (Cabut Poin)
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
