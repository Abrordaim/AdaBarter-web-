<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800">Reports & Analytics</h1>
        <select wire:model.live="period" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5">
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="all">All Time</option>
        </select>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm font-medium text-gray-500 mb-1">New Registrations</p>
            <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['users']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm font-medium text-gray-500 mb-1">Items Posted</p>
            <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['items']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm font-medium text-gray-500 mb-1">Offers Made</p>
            <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['offers']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm font-medium text-gray-500 mb-1">Completed Barters</p>
            <p class="text-3xl font-bold text-emerald-600">{{ number_format($stats['completed_offers']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Most Active Users -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Most Active Users (All Time)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                        <tr>
                            <th class="px-4 py-2">User</th>
                            <th class="px-4 py-2 text-center">Items Posted</th>
                            <th class="px-4 py-2 text-center">Offers Made</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($topUsers as $user)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-center">{{ $user->items_count }}</td>
                            <td class="px-4 py-3 text-center">{{ $user->sent_offers_count }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Cities -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Top Cities</h3>
            <div class="space-y-4">
                @forelse($topCities as $city)
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">{{ $city->city }}</span>
                    <span class="text-sm font-bold text-emerald-600">{{ $city->total }} users</span>
                </div>
                @empty
                <p class="text-sm text-gray-500">No city data available.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Platform Health -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4">Platform Health</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-orange-50 rounded-lg border border-orange-100 flex items-center justify-between">
                <span class="text-sm font-medium text-orange-800">Pending Offers</span>
                <span class="text-xl font-bold text-orange-600">{{ number_format($health['pending_offers']) }}</span>
            </div>
            <div class="p-4 bg-red-50 rounded-lg border border-red-100 flex items-center justify-between">
                <span class="text-sm font-medium text-red-800">Items Pending Moderation</span>
                <span class="text-xl font-bold text-red-600">{{ number_format($health['items_moderation']) }}</span>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg border border-blue-100 flex items-center justify-between">
                <span class="text-sm font-medium text-blue-800">Active Chat Rooms</span>
                <span class="text-xl font-bold text-blue-600">{{ number_format($health['active_chats']) }}</span>
            </div>
        </div>
    </div>
</div>
