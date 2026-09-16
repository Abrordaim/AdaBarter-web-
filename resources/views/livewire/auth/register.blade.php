<div>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <a href="/">
                <h2 class="text-3xl font-bold text-emerald-600">AdaBarter</h2>
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Create an account</h1>
            
            <form wire:submit="register">
                <!-- Name -->
                <div>
                    <label for="name" class="block font-medium text-sm text-gray-700">Name</label>
                    <input wire:model="name" id="name" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="text" name="name" required autofocus />
                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Email Address -->
                <div class="mt-4">
                    <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
                    <input wire:model="email" id="email" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="email" name="email" required />
                    @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <!-- Phone (Optional) -->
                <div class="mt-4">
                    <label for="phone" class="block font-medium text-sm text-gray-700">Phone (Optional)</label>
                    <input wire:model="phone" id="phone" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="text" name="phone" />
                    @error('phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <!-- City (Optional) -->
                <div class="mt-4">
                    <label for="city" class="block font-medium text-sm text-gray-700">City (Optional)</label>
                    <input wire:model="city" id="city" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="text" name="city" />
                    @error('city') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Password -->
                <div class="mt-4">
                    <label for="password" class="block font-medium text-sm text-gray-700">Password</label>
                    <input wire:model="password" id="password" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="password" name="password" required />
                    @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <!-- Confirm Password -->
                <div class="mt-4">
                    <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirm Password</label>
                    <input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="password" name="password_confirmation" required />
                </div>

                <div class="flex items-center justify-end mt-6">
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500" href="{{ route('login') }}">
                        Already registered?
                    </a>

                    <button type="submit" class="ms-4 inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150" wire:loading.attr="disabled">
                        <span wire:loading.remove>Register</span>
                        <span wire:loading>Registering...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
