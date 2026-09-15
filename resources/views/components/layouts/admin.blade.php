<x-layouts.app :title="$title ?? 'Admin Dashboard'">
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside class="w-64 bg-emerald-800 text-white flex flex-col transition-all duration-300">
            <div class="h-16 flex items-center px-6 bg-emerald-900 font-bold text-xl tracking-wider">
                AdaBarter Admin
            </div>
            
            <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
                @php
                    $links = [
                        ['label' => 'Dashboard', 'url' => '#', 'icon' => 'home'],
                        ['label' => 'Pengguna', 'url' => '#', 'icon' => 'users'],
                        ['label' => 'Barang & Moderasi', 'url' => '#', 'icon' => 'box'],
                        ['label' => 'Banner & Iklan', 'url' => '#', 'icon' => 'image'],
                        ['label' => 'Monetisasi', 'url' => '#', 'icon' => 'dollar-sign'],
                        ['label' => 'Laporan', 'url' => '#', 'icon' => 'file-text'],
                        ['label' => 'Pengaturan', 'url' => '#', 'icon' => 'settings'],
                    ];
                @endphp
                
                @foreach($links as $link)
                    <a href="{{ $link['url'] }}" class="flex items-center px-6 py-3 text-emerald-100 hover:bg-emerald-700 hover:text-white transition-colors">
                        <span class="mr-3 text-sm font-medium">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10">
                <div class="flex items-center">
                    <button class="text-gray-500 hover:text-gray-700 focus:outline-none lg:hidden">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-gray-800">{{ $title ?? 'Dashboard' }}</h1>
                </div>
                
                <div class="flex items-center">
                    <div class="relative">
                        <button class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none">
                            <span class="mr-2">Admin User</span>
                            <div class="h-8 w-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                                A
                            </div>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>
