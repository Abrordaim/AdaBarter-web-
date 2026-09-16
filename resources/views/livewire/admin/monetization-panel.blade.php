<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800">Monetization & Vouchers</h1>
    </div>

    <!-- Stats Grid -->
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

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
            <li class="mr-2">
                <button wire:click="setTab('transactions')" class="inline-flex p-4 border-b-2 rounded-t-lg {{ $activeTab === 'transactions' ? 'text-emerald-600 border-emerald-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    Transactions
                </button>
            </li>
            <li class="mr-2">
                <button wire:click="setTab('vouchers')" class="inline-flex p-4 border-b-2 rounded-t-lg {{ $activeTab === 'vouchers' ? 'text-emerald-600 border-emerald-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    Vouchers
                </button>
            </li>
        </ul>
    </div>

    @if($activeTab === 'transactions')
    <!-- Transactions Tab -->
    <div>
        <div class="mb-4 flex gap-4">
            <select wire:model.live="txTypeFilter" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 w-48">
                <option value="">All Types</option>
                <option value="pay_per_post">Pay Per Post</option>
                <option value="subscription">Subscription</option>
                <option value="boost">Boost</option>
            </select>
            <select wire:model.live="txStatusFilter" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 w-48">
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
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'refunded' => 'bg-gray-100 text-gray-800',
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
                        <tr><td colspan="6" class="px-6 py-4 text-center">No transactions found.</td></tr>
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

    @if($activeTab === 'vouchers')
    <!-- Vouchers Tab -->
    <div>
        <div class="mb-4">
            <button wire:click="createVoucher" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
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
                            <td class="px-6 py-4 text-center">
                                {{ $voucher->claimed_count }} / {{ $voucher->max_claims }}
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleVoucher({{ $voucher->id }})" class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $voucher->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $voucher->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                {{ $voucher->expired_at ? $voucher->expired_at->format('d M Y') : 'Never' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <!-- Could add edit button later -->
                                <span class="text-gray-400 text-xs">-</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-4 text-center">No vouchers found.</td></tr>
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

    <!-- Voucher Modal -->
    @if($showVoucherModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showVoucherModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <form wire:submit="saveVoucher">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Create Voucher</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Code</label>
                            <input type="text" wire:model="code" class="mt-1 block w-full border border-gray-300 rounded-md p-2 uppercase">
                            @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" wire:model="description" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Quota Given</label>
                                <input type="number" wire:model="quota_amount" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                                @error('quota_amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Max Claims</label>
                                <input type="number" wire:model="max_claims" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                                @error('max_claims') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expiry Date (Optional)</label>
                            <input type="datetime-local" wire:model="expired_at" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-medium text-white hover:bg-emerald-700 sm:ml-3 sm:w-auto sm:text-sm">
                            Save
                        </button>
                        <button type="button" wire:click="$set('showVoucherModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
