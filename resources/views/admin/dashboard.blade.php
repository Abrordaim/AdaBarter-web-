<x-layouts.admin title="Dashboard">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Stat Cards -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Pengguna</p>
                    <p class="text-2xl font-semibold text-gray-900">1,234</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Barang</p>
                    <p class="text-2xl font-semibold text-gray-900">5,678</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Transaksi Barter</p>
                    <p class="text-2xl font-semibold text-gray-900">892</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Aktivitas Terbaru</h2>
        <div class="space-y-4">
            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                <div class="h-10 w-10 rounded-full bg-emerald-200 flex items-center justify-center text-emerald-800 font-bold">
                    U
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-900">User baru mendaftar</p>
                    <p class="text-xs text-gray-500">5 menit yang lalu</p>
                </div>
            </div>
            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                <div class="h-10 w-10 rounded-full bg-blue-200 flex items-center justify-center text-blue-800 font-bold">
                    B
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-900">Barang baru diunggah untuk moderasi</p>
                    <p class="text-xs text-gray-500">15 menit yang lalu</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
