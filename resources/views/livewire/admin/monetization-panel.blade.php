<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800">Monetization & Vouchers</h1>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
            <p class="mb-2 text-sm font-medium text-gray-600">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-800">Rp {{ number_format($this->totalRevenue, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-purple-100 p-6">
            <p class="mb-2 text-sm font-medium text-gray-600">Active VIP Users</p>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($this->activeVipCount) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
            <p class="mb-2 text-sm font-medium text-gray-600">Active Vouchers</p>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($this->activeVouchersCount) }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-6">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
            @foreach([
                'transactions'   => 'Transactions',
                'vouchers'       => 'Vouchers',
                'slot_packages'  => 'Slot Packages',
                'vip_plans'      => 'VIP Plans',
                'boost_packages' => 'Boost Packages',
            ] as $tab => $label)
            <li class="mr-2">
                <button wire:click="setTab('{{ $tab }}')"
                    class="inline-flex p-4 border-b-2 rounded-t-lg {{ $activeTab === $tab ? 'text-emerald-600 border-emerald-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    {{ $label }}
                </button>
            </li>
            @endforeach
        </ul>
    </div>

    {{-- ───────────── TRANSACTIONS TAB ───────────── --}}
    @if($activeTab === 'transactions')
        <div>
            <div class="mb-4 flex gap-4">
                <select wire:model.live="txTypeFilter"
                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 w-48">
                    <option value="">All Types</option>
                    <option value="pay_per_post">Pay Per Post</option>
                    <option value="subscription">Subscription</option>
                    <option value="boost">Boost</option>
                </select>
                <select wire:model.live="txStatusFilter"
                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 w-48">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID / User</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Amount</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Method</th>
                                <th class="px-6 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($transactions as $tx)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">#{{ $tx->id }}</div>
                                        <div class="text-xs text-gray-500">{{ $tx->user->name ?? 'Unknown' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ str_replace('_', ' ', ucfirst($tx->type)) }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $sColors = [
                                                'completed' => 'bg-green-100 text-green-800',
                                                'pending'   => 'bg-yellow-100 text-yellow-800',
                                                'failed'    => 'bg-red-100 text-red-800',
                                                'refunded'  => 'bg-gray-100 text-gray-800',
                                            ];
                                        @endphp
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sColors[$tx->status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($tx->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">{{ $tx->payment_method ?? '-' }}</td>
                                    <td class="px-6 py-4 text-xs">{{ $tx->created_at->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center">No transactions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($transactions->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- ───────────── VOUCHERS TAB ───────────── --}}
    @if($activeTab === 'vouchers')
        <div>
            <div class="mb-4">
                <button wire:click="createVoucher"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                    + Create Voucher
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">Code</th>
                                <th class="px-6 py-3 text-center">Quota Added</th>
                                <th class="px-6 py-3 text-center">Claims</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Expiry</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($vouchers as $voucher)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-bold text-gray-900">{{ $voucher->code }}</td>
                                    <td class="px-6 py-4 text-center text-emerald-600 font-medium">+{{ $voucher->quota_amount }}</td>
                                    <td class="px-6 py-4 text-center">{{ $voucher->claimed_count }} / {{ $voucher->max_claims }}</td>
                                    <td class="px-6 py-4">
                                        <button wire:click="toggleVoucher({{ $voucher->id }})"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $voucher->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $voucher->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        {{ $voucher->expired_at ? $voucher->expired_at->format('d M Y') : 'Never' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-gray-400 text-xs">-</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center">No vouchers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($vouchers->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">{{ $vouchers->links() }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- ───────────── SLOT PACKAGES TAB ───────────── --}}
    @if($activeTab === 'slot_packages')
        <div>
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">Kelola paket pembelian kuota slot posting barang.</p>
                <button wire:click="createSlot"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                    + Tambah Paket Slot
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">Nama Paket</th>
                                <th class="px-6 py-3 text-center">Jumlah Slot</th>
                                <th class="px-6 py-3 text-right">Harga</th>
                                <th class="px-6 py-3">Badge</th>
                                <th class="px-6 py-3 text-center">Urutan</th>
                                <th class="px-6 py-3 text-center">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($slotPackages as $pkg)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-400 text-xs">#{{ $pkg->id }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $pkg->name }}</td>
                                    <td class="px-6 py-4 text-center font-bold text-emerald-600">+{{ $pkg->slots }}</td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-800">
                                        Rp {{ number_format($pkg->price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($pkg->badge)
                                            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium">{{ $pkg->badge }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-500">{{ $pkg->sort_order }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <button wire:click="toggleSlot({{ $pkg->id }})"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pkg->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $pkg->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button wire:click="editSlot({{ $pkg->id }})"
                                                class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 font-medium">
                                                Edit
                                            </button>
                                            <button wire:click="deleteSlot({{ $pkg->id }})"
                                                wire:confirm="Hapus paket slot ini?"
                                                class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-400">
                                        Belum ada paket slot. Klik "+ Tambah Paket Slot" untuk memulai.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($slotPackages->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">{{ $slotPackages->links() }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- ───────────── VIP PLANS TAB ───────────── --}}
    @if($activeTab === 'vip_plans')
        <div>
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">Kelola paket langganan VIP dan harga masa aktif.</p>
                <button wire:click="createVip"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                    + Tambah Paket VIP
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">Nama Paket</th>
                                <th class="px-6 py-3 text-center">Masa Aktif</th>
                                <th class="px-6 py-3 text-right">Harga</th>
                                <th class="px-6 py-3">Tag / Badge</th>
                                <th class="px-6 py-3 text-center">Urutan</th>
                                <th class="px-6 py-3 text-center">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($vipPlans as $plan)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-400 text-xs">#{{ $plan->id }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $plan->name }}</td>
                                    <td class="px-6 py-4 text-center text-gray-700">
                                        {{ $plan->duration_days }} hari
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-800">
                                        Rp {{ number_format($plan->price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            @if($plan->tag)
                                                <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs font-medium w-fit">{{ $plan->tag }}</span>
                                            @endif
                                            @if($plan->badge)
                                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium w-fit">{{ $plan->badge }}</span>
                                            @endif
                                            @if(!$plan->tag && !$plan->badge)
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-500">{{ $plan->sort_order }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <button wire:click="toggleVip({{ $plan->id }})"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button wire:click="editVip({{ $plan->id }})"
                                                class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 font-medium">
                                                Edit
                                            </button>
                                            <button wire:click="deleteVip({{ $plan->id }})"
                                                wire:confirm="Hapus paket VIP ini?"
                                                class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-400">
                                        Belum ada paket VIP. Klik "+ Tambah Paket VIP" untuk memulai.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($vipPlans->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">{{ $vipPlans->links() }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- ───────────── BOOST PACKAGES TAB ───────────── --}}
    @if($activeTab === 'boost_packages')
        <div>
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">Kelola paket iklan sorotan (boost) untuk barang pengguna.</p>
                <button wire:click="createBoost"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                    + Tambah Paket Boost
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">Nama Paket</th>
                                <th class="px-6 py-3 text-center">Durasi (Hari)</th>
                                <th class="px-6 py-3 text-right">Harga</th>
                                <th class="px-6 py-3">Tag</th>
                                <th class="px-6 py-3">Deskripsi</th>
                                <th class="px-6 py-3 text-center">Urutan</th>
                                <th class="px-6 py-3 text-center">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($boostPackages as $pkg)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-400 text-xs">#{{ $pkg->id }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $pkg->name }}</td>
                                    <td class="px-6 py-4 text-center font-bold text-amber-600">
                                        {{ $pkg->days }} hari
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-800">
                                        Rp {{ number_format($pkg->price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($pkg->tag)
                                            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium">{{ $pkg->tag }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate">
                                        {{ $pkg->description ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-500">{{ $pkg->sort_order }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <button wire:click="toggleBoost({{ $pkg->id }})"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pkg->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $pkg->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button wire:click="editBoost({{ $pkg->id }})"
                                                class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 font-medium">
                                                Edit
                                            </button>
                                            <button wire:click="deleteBoost({{ $pkg->id }})"
                                                wire:confirm="Hapus paket boost ini?"
                                                class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-400">
                                        Belum ada paket boost. Klik "+ Tambah Paket Boost" untuk memulai.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($boostPackages->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">{{ $boostPackages->links() }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- MODALS                                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}

    {{-- Voucher Modal --}}
    @if($showVoucherModal)
        <div class="fixed inset-0 z-100 overflow-y-auto bg-gray-900/75 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl text-left overflow-hidden shadow-xl sm:max-w-md w-full">
                <form wire:submit="saveVoucher">
                    <div class="bg-white px-6 pt-6 pb-4 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ $voucherId ? 'Edit Voucher' : 'Create Voucher' }}</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Code</label>
                            <input type="text" wire:model="code"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2 uppercase">
                            @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" wire:model="description"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Quota Given</label>
                                <input type="number" wire:model="quota_amount"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                                @error('quota_amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Max Claims</label>
                                <input type="number" wire:model="max_claims"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                                @error('max_claims') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expiry Date (Optional)</label>
                            <input type="datetime-local" wire:model="expired_at"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex flex-row-reverse gap-3">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md px-4 py-2 bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700">
                            Save
                        </button>
                        <button type="button" wire:click="$set('showVoucherModal', false)"
                            class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Slot Package Modal --}}
    @if($showSlotModal)
        <div class="fixed inset-0 z-100 overflow-y-auto bg-gray-900/75 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl text-left overflow-hidden shadow-xl sm:max-w-lg w-full">
                <form wire:submit="saveSlot">
                    <div class="bg-white px-6 pt-6 pb-4 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $editingSlotId ? 'Edit Paket Slot' : 'Tambah Paket Slot' }}
                        </h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama Paket</label>
                            <input type="text" wire:model="slotName" placeholder="contoh: +3 Slot Barang"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                            @error('slotName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jumlah Slot</label>
                                <input type="number" wire:model="slotSlots" min="1" placeholder="3"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                @error('slotSlots') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                                <input type="number" wire:model="slotPrice" min="0" placeholder="25000"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                @error('slotPrice') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Badge <span class="text-gray-400">(opsional)</span></label>
                            <input type="text" wire:model="slotBadge" placeholder="contoh: Hemat Rp 5.000"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Urutan Tampil</label>
                                <input type="number" wire:model="slotSortOrder" min="0"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 sm:text-sm">
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="slotIsActive" class="rounded text-emerald-600">
                                    <span class="text-sm font-medium text-gray-700">Aktif (tampil di mobile)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex flex-row-reverse gap-3">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md px-4 py-2 bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700">
                            Simpan
                        </button>
                        <button type="button" wire:click="$set('showSlotModal', false)"
                            class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- VIP Plan Modal --}}
    @if($showVipModal)
        <div class="fixed inset-0 z-100 overflow-y-auto bg-gray-900/75 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl text-left overflow-hidden shadow-xl sm:max-w-lg w-full">
                <form wire:submit="saveVip">
                    <div class="bg-white px-6 pt-6 pb-4 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $editingVipId ? 'Edit Paket VIP' : 'Tambah Paket VIP' }}
                        </h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama Paket</label>
                            <input type="text" wire:model="vipName" placeholder="contoh: VIP 3 Bulan"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                            @error('vipName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Masa Aktif (hari)</label>
                                <input type="number" wire:model="vipDurationDays" min="1" placeholder="30"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                @error('vipDurationDays') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                                <input type="number" wire:model="vipPrice" min="0" placeholder="49000"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                @error('vipPrice') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tag <span class="text-gray-400">(opsional)</span></label>
                                <input type="text" wire:model="vipTag" placeholder="Paling Populer"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Badge <span class="text-gray-400">(opsional)</span></label>
                                <input type="text" wire:model="vipBadge" placeholder="Hemat 20%"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Fitur / Keuntungan
                                <span class="text-gray-400 font-normal">(satu baris per fitur)</span>
                            </label>
                            <textarea wire:model="vipFeatures" rows="4"
                                placeholder="Posting barang tanpa batas&#10;Lencana VIP emas eksklusif&#10;Prioritas pencarian"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Urutan Tampil</label>
                                <input type="number" wire:model="vipSortOrder" min="0"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 sm:text-sm">
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="vipIsActive" class="rounded text-emerald-600">
                                    <span class="text-sm font-medium text-gray-700">Aktif (tampil di mobile)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex flex-row-reverse gap-3">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md px-4 py-2 bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700">
                            Simpan
                        </button>
                        <button type="button" wire:click="$set('showVipModal', false)"
                            class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Boost Package Modal --}}
    @if($showBoostModal)
        <div class="fixed inset-0 z-100 overflow-y-auto bg-gray-900/75 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl text-left overflow-hidden shadow-xl sm:max-w-lg w-full">
                <form wire:submit="saveBoost">
                    <div class="bg-white px-6 pt-6 pb-4 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $editingBoostId ? 'Edit Paket Boost' : 'Tambah Paket Boost' }}
                        </h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama Paket</label>
                            <input type="text" wire:model="boostName" placeholder="contoh: Boost 7 Hari"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                            @error('boostName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Durasi (hari)</label>
                                <input type="number" wire:model="boostDays" min="1" placeholder="7"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                @error('boostDays') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                                <input type="number" wire:model="boostPrice" min="0" placeholder="29000"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                @error('boostPrice') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tag <span class="text-gray-400">(opsional)</span></label>
                            <input type="text" wire:model="boostTag" placeholder="contoh: Paling Diminati"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Deskripsi Singkat <span class="text-gray-400">(opsional)</span></label>
                            <input type="text" wire:model="boostDescription" placeholder="contoh: Maksimal eksposur barter selama 1 minggu penuh"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Urutan Tampil</label>
                                <input type="number" wire:model="boostSortOrder" min="0"
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 sm:text-sm">
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="boostIsActive" class="rounded text-emerald-600">
                                    <span class="text-sm font-medium text-gray-700">Aktif (tampil di mobile)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex flex-row-reverse gap-3">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md px-4 py-2 bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700">
                            Simpan
                        </button>
                        <button type="button" wire:click="$set('showBoostModal', false)"
                            class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>