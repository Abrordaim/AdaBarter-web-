<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manajemen Kategori</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola kategori barang barter yang tersedia di aplikasi.</p>
        </div>
        <button wire:click="create" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm transition-colors whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Kategori
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-5 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="relative max-w-xs">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama kategori..."
                   class="block w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3 w-12">#</th>
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Slug</th>
                        <th class="px-5 py-3">Deskripsi</th>
                        <th class="px-5 py-3 text-center">Jumlah Barang</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($categories as $cat)
                    <tr class="bg-white hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4 text-gray-400 text-xs">{{ $cat->id }}</td>

                        {{-- Nama & Ikon --}}
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                @if($cat->icon)
                                    <span class="text-2xl leading-none">{{ $cat->icon }}</span>
                                @else
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs font-bold">
                                        {{ strtoupper(substr($cat->name, 0, 2)) }}
                                    </div>
                                @endif
                                <span class="font-semibold text-gray-900">{{ $cat->name }}</span>
                            </div>
                        </td>

                        {{-- Slug --}}
                        <td class="px-5 py-4">
                            <code class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $cat->slug }}</code>
                        </td>

                        {{-- Deskripsi --}}
                        <td class="px-5 py-4 max-w-xs">
                            <p class="text-xs text-gray-500 truncate" title="{{ $cat->description }}">
                                {{ $cat->description ?: '—' }}
                            </p>
                        </td>

                        {{-- Jumlah barang --}}
                        <td class="px-5 py-4 text-center">
                            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-bold
                                {{ $cat->items_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $cat->items_count }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-5 py-4 text-center">
                            <button wire:click="toggleActive({{ $cat->id }})"
                                    class="px-3 py-1 rounded-full text-xs font-semibold cursor-pointer transition-colors
                                        {{ $cat->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                {{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </td>

                        {{-- Aksi --}}
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="edit({{ $cat->id }})"
                                        class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="delete({{ $cat->id }})"
                                        wire:confirm="Hapus kategori '{{ $cat->name }}'? Pastikan tidak ada barang yang menggunakan kategori ini."
                                        class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="text-4xl mb-3">📂</div>
                            <p class="text-gray-500 font-medium">Belum ada kategori</p>
                            <p class="text-gray-400 text-xs mt-1">Klik "Tambah Kategori" untuk memulai.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $categories->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Tambah / Edit --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <form wire:submit="save">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $categoryId ? 'Edit Kategori' : 'Tambah Kategori Baru' }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="px-6 py-5 space-y-4">
                    {{-- Ikon & Nama (side by side) --}}
                    <div class="flex gap-3">
                        <div class="w-20">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Ikon (Emoji)</label>
                            <input type="text" wire:model="icon" placeholder="🛍️" maxlength="4"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-center text-2xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('icon') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Nama Kategori <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="name" placeholder="Contoh: Elektronik" maxlength="100"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Slug --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Slug (URL) <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="slug" placeholder="elektronik" maxlength="120"
                               class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-400 mt-0.5">Otomatis terisi dari nama. Hanya huruf kecil, angka, dan strip.</p>
                        @error('slug') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Deskripsi --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Deskripsi (Opsional)</label>
                        <textarea wire:model="description" rows="2" maxlength="500"
                                  placeholder="Deskripsi singkat kategori ini..."
                                  class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none"></textarea>
                        @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Status --}}
                    <div class="flex items-center gap-3 pt-1">
                        <button type="button" wire:click="$toggle('is_active')"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $is_active ? 'bg-emerald-500' : 'bg-gray-300' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                        <span class="text-sm font-medium text-gray-700">{{ $is_active ? 'Kategori Aktif' : 'Kategori Nonaktif' }}</span>
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
                        {{ $categoryId ? 'Simpan Perubahan' : 'Tambah Kategori' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
