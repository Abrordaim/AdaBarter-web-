<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800">Item Moderation</h1>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-col md:flex-row gap-4 justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="flex-1 min-w-[200px]">
            <input wire:model.live.debounce.300ms="search" type="text" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" placeholder="Search by title...">
        </div>
        <div class="flex gap-4">
            <select wire:model.live="categoryFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 min-w-[150px]">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 min-w-[150px]">
                <option value="">All Statuses</option>
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
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-6 py-3">Title & Category</th>
                        <th class="px-6 py-3">Owner</th>
                        <th class="px-6 py-3">Condition</th>
                        <th class="px-6 py-3">Est. Price</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $item)
                    <tr class="bg-white hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                @php
                                    $images = is_string($item->images) ? json_decode($item->images, true) : $item->images;
                                    $firstImage = is_array($images) && count($images) > 0 ? $images[0] : null;
                                @endphp
                                @if($firstImage)
                                    <img src="{{ Storage::url($firstImage) }}" class="w-10 h-10 rounded object-cover border border-gray-200">
                                @else
                                    <div class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                @endif
                                <div>
                                    <div class="font-medium text-gray-900">{{ $item->title }}</div>
                                    <div class="text-xs text-gray-500">{{ $item->category->name ?? 'Uncategorized' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            {{ $item->user->name ?? 'Unknown' }}
                        </td>
                        <td class="px-6 py-4">
                            {{ ucfirst($item->condition) }}
                        </td>
                        <td class="px-6 py-4 font-medium">
                            Rp {{ number_format($item->estimated_price, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-gray-100 text-gray-800',
                                    'moderated' => 'bg-red-100 text-red-800',
                                    'traded' => 'bg-blue-100 text-blue-800',
                                ];
                                $color = $statusColors[$item->status] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">
                            {{ $item->created_at->format('d M Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            @if($item->status !== 'active')
                            <button wire:click="approveItem({{ $item->id }})" class="text-green-600 hover:text-green-900 font-medium text-xs p-1">
                                Approve
                            </button>
                            @endif
                            @if($item->status !== 'moderated')
                            <button wire:click="moderateItem({{ $item->id }})" class="text-yellow-600 hover:text-yellow-900 font-medium text-xs p-1">
                                Moderate
                            </button>
                            @endif
                            <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Are you sure you want to delete this item?" class="text-red-600 hover:text-red-900 font-medium text-xs p-1">
                                Delete
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            No items found matching your criteria.
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
</div>
