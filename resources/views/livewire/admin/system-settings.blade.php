<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">System Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Manage global platform configurations.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- App Identity Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-800">App Identity</h2>
            </div>
            
            <form wire:submit="saveIdentity" class="p-6">
                @if (session()->has('notify_identity'))
                    <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        {{ session('notify_identity') }}
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">App Name</label>
                        <input type="text" wire:model="appName" class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        @error('appName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">App Description</label>
                        <textarea wire:model="appDescription" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"></textarea>
                        @error('appDescription') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Support Email</label>
                        <input type="email" wire:model="supportEmail" class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        @error('supportEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium transition-colors">
                        Save Identity
                    </button>
                </div>
            </form>
        </div>

        <!-- Platform Parameters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-800">Platform Parameters</h2>
            </div>
            
            <form wire:submit="savePlatform" class="p-6">
                @if (session()->has('notify_platform'))
                    <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        {{ session('notify_platform') }}
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Default Free Post Quota</label>
                        <div class="mt-1 flex items-center">
                            <input type="number" wire:model="defaultPostQuota" class="block w-32 border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                            <span class="ml-3 text-sm text-gray-500">posts per user (Current: {{ $defaultPostQuota }})</span>
                        </div>
                        @error('defaultPostQuota') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">VIP Monthly Price (Rp)</label>
                        <input type="number" wire:model="vipMonthlyPrice" class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        @error('vipMonthlyPrice') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Boost Price Per Day (Rp)</label>
                        <input type="number" wire:model="boostPricePerDay" class="mt-1 block w-full border border-gray-300 rounded-md p-2.5 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        @error('boostPricePerDay') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium transition-colors">
                        Save Parameters
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
