<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Barang & Moderasi</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola data barang barter, moderasi kelayakan, dan pantau status barang pengguna.</p>
        </div>
        <button wire:click="create" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm transition-colors whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Barang
        </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-col md:flex-row gap-4 justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="flex-1 min-w-[200px]">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" class="block w-full p-2.5 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" placeholder="Cari judul, deskripsi, kota...">
            </div>
        </div>
        <div class="flex flex-wrap sm:flex-nowrap gap-3">
            <select wire:model.live="categoryFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 min-w-[150px]">
                <option value="">Semua Kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 min-w-[150px]">
                <option value="">Semua Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="moderated">Moderated</option>
                <option value="traded">Traded</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">Barang & Kategori</th>
                        <th class="px-5 py-3">Pemilik</th>
                        <th class="px-5 py-3">Kondisi</th>
                        <th class="px-5 py-3">Taksiran Harga</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3">Kota</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $item)
                    <tr class="bg-white hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center space-x-3">
                                @php
                                    $images = is_string($item->images) ? json_decode($item->images, true) : $item->images;
                                    $firstImage = is_array($images) && count($images) > 0 ? $images[0] : null;
                                @endphp
                                @if($firstImage)
                                    <img src="{{ Storage::url($firstImage) }}" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shrink-0">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                @endif
                                <div>
                                    <button wire:click="viewDetails({{ $item->id }})" class="font-semibold text-gray-900 hover:text-emerald-600 text-left line-clamp-1 cursor-pointer">
                                        {{ $item->title }}
                                    </button>
                                    <div class="text-xs text-emerald-700 font-medium mt-0.5">
                                        {{ $item->category->name ?? 'Tanpa Kategori' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium text-gray-900">{{ $item->user->name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $item->user->email ?? '' }}</div>
                        </td>
                        <td class="px-5 py-4 text-xs">
                            @php
                                $conditionLabels = [
                                    'baru' => ['label' => 'Baru', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                    'bekas_seperti_baru' => ['label' => 'Seperti Baru', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                                    'bekas_baik' => ['label' => 'Bekas Baik', 'class' => 'bg-gray-100 text-gray-700 border-gray-200'],
                                    'bekas_layak_pakai' => ['label' => 'Layak Pakai', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                ];
                                $cond = $conditionLabels[$item->condition] ?? ['label' => ucfirst($item->condition), 'class' => 'bg-gray-100 text-gray-600 border-gray-200'];
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded border text-[11px] font-medium {{ $cond['class'] }}">
                                {{ $cond['label'] }}
                            </span>
                        </td>
                        <td class="px-5 py-4 font-semibold text-gray-800 text-xs">
                            {{ $item->estimated_price ? 'Rp ' . number_format($item->estimated_price, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-5 py-4 text-center">
                            @php
                                $statusColors = [
                                    'active' => 'bg-emerald-100 text-emerald-800',
                                    'inactive' => 'bg-gray-100 text-gray-800',
                                    'moderated' => 'bg-red-100 text-red-800',
                                    'traded' => 'bg-blue-100 text-blue-800',
                                ];
                                $color = $statusColors[$item->status] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $color }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-gray-600 text-xs">
                            {{ $item->city ?: '—' }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- Quick View --}}
                                <button wire:click="viewDetails({{ $item->id }})" class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors" title="Lihat Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>

                                {{-- Edit --}}
                                <button wire:click="edit({{ $item->id }})" class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Data Barang">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>

                                {{-- Moderation quick actions --}}
                                @if($item->status !== 'active')
                                <button wire:click="approveItem({{ $item->id }})" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="Setujui (Active)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                @endif

                                @if($item->status !== 'moderated')
                                <button wire:click="moderateItem({{ $item->id }})" class="p-1.5 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition-colors" title="Tandai Moderated">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </button>
                                @endif

                                {{-- Delete --}}
                                <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Hapus barang '{{ $item->title }}'?" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" title="Hapus Barang">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                            <div class="text-3xl mb-2">📦</div>
                            <p class="font-medium">Tidak ada data barang yang ditemukan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $items->links() }}
        </div>
    </div>

    {{-- Modal Tambah / Edit Barang --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
            <form wire:submit="save">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $itemId ? 'Edit Data Barang' : 'Tambah Barang Baru' }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    {{-- Pemilik & Kategori --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Pemilik Barang <span class="text-red-500">*</span></label>
                            <select wire:model="user_id" class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">-- Pilih Pemilik (User) --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('user_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                            <select wire:model="category_id" class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Judul Barang --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Barang <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="title" placeholder="Contoh: Sepeda Lipat Polygon Urbano"
                               class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Kondisi, Taksiran Harga, Status --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kondisi <span class="text-red-500">*</span></label>
                            <select wire:model="condition" class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="baru">Baru</option>
                                <option value="bekas_seperti_baru">Seperti Baru (95-99%)</option>
                                <option value="bekas_baik">Bekas Baik (Normal)</option>
                                <option value="bekas_layak_pakai">Layak Pakai</option>
                            </select>
                            @error('condition') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Taksiran Nilai (Rp)</label>
                            <input type="number" min="0" wire:model="estimated_price" placeholder="1500000"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('estimated_price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                            <select wire:model="status" class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="active">Active (Tayang)</option>
                                <option value="inactive">Inactive</option>
                                <option value="moderated">Moderated (Ditahan)</option>
                                <option value="traded">Traded (Selesai Barter)</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Barang Yang Diinginkan (Barter Target) --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Barang Yang Dicari / Diinginkan</label>
                        <input type="text" wire:model="desired_items" placeholder="Contoh: Gitar Akustik / HP Android sepadan"
                               class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        @error('desired_items') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Kota & Lokasi Spesifik --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kota</label>
                            <input type="text" wire:model="city" placeholder="Jakarta Selatan"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('city') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Lokasi Detail (Kecamatan/Area)</label>
                            <input type="text" wire:model="location" placeholder="Tebet Timur"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('location') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Deskripsi Lengkap <span class="text-red-500">*</span></label>
                        <textarea wire:model="description" rows="3" placeholder="Jelaskan kondisi fisik, fungsi, kelengkapan, dll..."
                                  class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none"></textarea>
                        @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Gambar --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Foto Barang (Opsional)</label>

                        {{-- Existing Images --}}
                        @if(!empty($existing_images))
                        <div class="mb-3">
                            <span class="text-xs text-gray-500 mb-1 block">Foto saat ini:</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach($existing_images as $idx => $img)
                                <div class="relative w-20 h-20 rounded-lg border border-gray-200 overflow-hidden group">
                                    <img src="{{ Storage::url($img) }}" class="w-full h-full object-cover">
                                    <button type="button" wire:click="removeExistingImage({{ $idx }})"
                                            class="absolute top-1 right-1 bg-red-600/80 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-700 transition-colors"
                                            title="Hapus foto ini">
                                        ✕
                                    </button>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Upload New Images --}}
                        <input type="file" wire:model="new_images" multiple accept="image/*"
                               class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                        <p class="text-[11px] text-gray-400 mt-1">Format: JPG, PNG, WEBP (Maksimal 3MB per file).</p>
                        @error('new_images.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-2xl border-t border-gray-100">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-colors">
                        {{ $itemId ? 'Simpan Perubahan' : 'Tambah Barang' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Modal Detail Barang --}}
    @if($showDetailModal && $selectedItem)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $selectedItem->title }}</h3>
                    <p class="text-xs text-emerald-700 font-semibold">{{ $selectedItem->category->name ?? 'Kategori tidak diketahui' }}</p>
                </div>
                <button type="button" wire:click="$set('showDetailModal', false)"
                        class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full hover:bg-gray-200 flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">
                {{-- Foto Galeri --}}
                @php
                    $detailImages = is_string($selectedItem->images) ? json_decode($selectedItem->images, true) : $selectedItem->images;
                @endphp
                @if(is_array($detailImages) && count($detailImages) > 0)
                <div>
                    <span class="text-xs font-semibold text-gray-600 block mb-2">Galeri Foto Barang ({{ count($detailImages) }})</span>
                    <div class="flex gap-2 overflow-x-auto pb-2">
                        @foreach($detailImages as $dImg)
                            <a href="{{ Storage::url($dImg) }}" target="_blank" class="shrink-0 w-32 h-32 rounded-xl border border-gray-200 overflow-hidden">
                                <img src="{{ Storage::url($dImg) }}" class="w-full h-full object-cover hover:scale-105 transition-transform">
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Spesifikasi Ringkas --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-gray-50 p-3 rounded-xl border border-gray-100 text-center">
                    <div>
                        <span class="text-[11px] text-gray-400 block">Kondisi</span>
                        <span class="text-xs font-bold text-gray-800">{{ ucfirst(str_replace('_', ' ', $selectedItem->condition)) }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-gray-400 block">Taksiran Nilai</span>
                        <span class="text-xs font-bold text-emerald-700">{{ $selectedItem->estimated_price ? 'Rp ' . number_format($selectedItem->estimated_price, 0, ',', '.') : '—' }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-gray-400 block">Status</span>
                        <span class="text-xs font-bold text-gray-800">{{ ucfirst($selectedItem->status) }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-gray-400 block">Kota</span>
                        <span class="text-xs font-bold text-gray-800">{{ $selectedItem->city ?: '—' }}</span>
                    </div>
                </div>

                {{-- Pemilik --}}
                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-emerald-600 font-semibold block uppercase tracking-wider">Pemilik Barang</span>
                        <div class="text-sm font-bold text-gray-900">{{ $selectedItem->user->name ?? '—' }}</div>
                        <div class="text-xs text-gray-500">{{ $selectedItem->user->email ?? '' }} • 📞 {{ $selectedItem->user->phone ?? 'Tidak ada no. telp' }}</div>
                    </div>
                    <span class="text-xs font-semibold px-2 py-1 rounded bg-white text-emerald-800 border border-emerald-200">
                        {{ $selectedItem->user->city ?: 'Indonesia' }}
                    </span>
                </div>

                {{-- Keinginan Barter --}}
                @if($selectedItem->desired_items)
                <div>
                    <span class="text-xs font-semibold text-gray-600 block mb-1">Mencari Barter:</span>
                    <p class="text-sm bg-blue-50 border border-blue-100 p-2.5 rounded-lg text-blue-900 font-medium">
                        🔄 {{ $selectedItem->desired_items }}
                    </p>
                </div>
                @endif

                {{-- Deskripsi --}}
                <div>
                    <span class="text-xs font-semibold text-gray-600 block mb-1">Deskripsi Lengkap:</span>
                    <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100 whitespace-pre-wrap leading-relaxed">
                        {{ $selectedItem->description }}
                    </div>
                </div>

                {{-- Tawaran Barter Masuk --}}
                <div>
                    <span class="text-xs font-semibold text-gray-600 block mb-2">Tawaran Barter Masuk ({{ $selectedItem->offersAsTarget->count() }})</span>
                    @if($selectedItem->offersAsTarget->count() > 0)
                        <div class="space-y-2">
                            @foreach($selectedItem->offersAsTarget as $offer)
                            <div class="p-2.5 rounded-lg border border-gray-200 bg-white flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-semibold text-gray-900">{{ $offer->user->name ?? 'Penawar' }}</span>
                                    <span class="text-gray-400">menawarkan:</span>
                                    <span class="font-medium text-emerald-700">{{ $offer->offererItem->title ?? 'Uang Tambahan' }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700">
                                    {{ ucfirst($offer->status) }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400 italic">Belum ada tawaran barter masuk untuk barang ini.</p>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-between items-center px-6 py-4 bg-gray-50 border-t border-gray-100">
                <button type="button" wire:click="edit({{ $selectedItem->id }}); $set('showDetailModal', false)"
                        class="px-4 py-2 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                    ✏️ Edit Barang Ini
                </button>
                <button type="button" wire:click="$set('showDetailModal', false)"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
