<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manajemen Pengguna</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola data seluruh akun pengguna, moderator, dan administrator.</p>
        </div>
        <button wire:click="create" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm transition-colors whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah User
        </button>
    </div>

    <!-- Filters & Search -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4 justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="flex-1 max-w-md">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" class="block w-full p-2.5 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" placeholder="Cari nama, email, telepon, kota...">
            </div>
        </div>
        <div>
            <select wire:model.live="roleFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5">
                <option value="">Semua Role</option>
                <option value="user">User Biasa</option>
                <option value="admin">Admin</option>
                <option value="super_admin">Super Admin</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3">Nama & Email</th>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3 text-center">Status VIP</th>
                        <th class="px-6 py-3 text-center">Kuota (Free/Bonus)</th>
                        <th class="px-6 py-3 text-center">Statistik (Barang/Tawaran)</th>
                        <th class="px-6 py-3">Kota</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="bg-white hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-sm shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                    @if($user->phone)
                                        <div class="text-[11px] text-gray-400">📞 {{ $user->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold 
                                {{ $user->role === 'super_admin' ? 'bg-purple-100 text-purple-800' : 
                                  ($user->role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700') }}">
                                {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="toggleVip({{ $user->id }})" class="px-2.5 py-1 rounded-full text-xs font-semibold cursor-pointer transition-colors
                                {{ $user->is_vip ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                {{ $user->is_vip ? '⭐ VIP' : 'Regular' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-medium text-gray-700 bg-gray-100 px-2 py-1 rounded">
                                {{ $user->free_post_quota }} / {{ $user->bonus_post_quota }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center font-medium">
                            <span class="text-emerald-700">{{ $user->items_count }}</span>
                            <span class="text-gray-400 text-xs">barang</span> / 
                            <span class="text-blue-700">{{ $user->sent_offers_count }}</span>
                            <span class="text-gray-400 text-xs">tawaran</span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 text-xs">
                            {{ $user->city ?: '—' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- Edit button --}}
                                <button wire:click="edit({{ $user->id }})" class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Data User">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>

                                {{-- Super admin quick role changer & delete --}}
                                @if(auth()->user() && auth()->user()->role === 'super_admin' && $user->id !== auth()->id())
                                    <button wire:click="deleteUser({{ $user->id }})" wire:confirm="Hapus akun '{{ $user->name }}' secara permanen?" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" title="Hapus User">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                            <div class="text-3xl mb-2">👥</div>
                            <p class="font-medium">Tidak ada data pengguna yang sesuai kriteria.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->links() }}
        </div>
    </div>

    {{-- Modal Tambah / Edit User --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
            <form wire:submit="save">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $userId ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    {{-- Nama Lengkap --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" placeholder="Contoh: Budi Santoso"
                               class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Email & Role (Side by side) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" wire:model="email" placeholder="email@contoh.com"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Role Akun <span class="text-red-500">*</span></label>
                            <select wire:model="role" class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="user">User Biasa</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                            @error('role') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Password {!! $userId ? '<span class="text-gray-400 font-normal">(Kosongkan jika tidak diubah)</span>' : '<span class="text-red-500">*</span>' !!}
                        </label>
                        <input type="password" wire:model="password" placeholder="Minimal 6 karakter"
                               class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Telepon & Kota --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Telepon / WA</label>
                            <input type="text" wire:model="phone" placeholder="081234567890"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kota Domisili</label>
                            <input type="text" wire:model="city" placeholder="Jakarta Selatan"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('city') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Kuota Posting --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kuota Posting Gratis</label>
                            <input type="number" min="0" wire:model="free_post_quota"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('free_post_quota') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kuota Posting Bonus</label>
                            <input type="number" min="0" wire:model="bonus_post_quota"
                                   class="block w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('bonus_post_quota') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Status VIP --}}
                    <div class="flex items-center gap-3 pt-1">
                        <button type="button" wire:click="$toggle('is_vip')"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $is_vip ? 'bg-amber-500' : 'bg-gray-300' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $is_vip ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                        <span class="text-sm font-medium text-gray-700">{{ $is_vip ? '⭐ Akun VIP Aktif' : 'Akun Regular' }}</span>
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
                        {{ $userId ? 'Simpan Perubahan' : 'Tambah User' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
