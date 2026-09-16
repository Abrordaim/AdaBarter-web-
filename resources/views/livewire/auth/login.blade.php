<div>
    <div class="flex h-screen w-screen bg-white items-center justify-center gap-2 mx-6">
        <div class="relative w-1/2 h-160 rounded-xl bg-primary">
            <img src="{{ asset('barter.jpeg') }}" alt="Barter" class="w-full h-full object-cover rounded-xl">
            <img src="{{ asset('logo.png') }}" alt="AdaBarter" class="absolute m-4 top-0 w-20 bg-slate-50 rounded-xl">
            <div class="absolute bottom-0 p-10">
                <h1 class="text-4xl text-primary bg-slate-50 p-6 rounded-2xl font-sans font-bold">AdaBarter</h1>
            </div>
        </div>
        <div class="min-h-screen w-1/2 flex flex-col sm:justify-center items-center pt-6 sm:pt-0 ">
           

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Login </h1>
                
                <form wire:submit="login">
                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
                        <input wire:model="email" id="email" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="email" name="email" required autofocus />
                        @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <label for="password" class="block font-medium text-sm text-gray-700">Password</label>
                        <input wire:model="password" id="password" class="block mt-1 w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm p-2 border" type="password" name="password" required />
                        @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="block mt-4">
                        <label for="remember_me" class="inline-flex items-center">
                            <input wire:model="remember" id="remember_me" type="checkbox" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500" name="remember">
                            <span class="ms-2 text-sm text-gray-600">Remember me</span>
                        </label>
                    </div>

                    <div class="flex flex-col items-center mt-4">

                        <button type="submit" class="ms-4 w-60 inline-flex items-center justify-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150" wire:loading.attr="disabled">
                            <span wire:loading.remove>Log in</span>
                            <span wire:loading>Logging in...</span>
                        </button>
                        <a class="underline mt-6 text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500" href="{{ route('register') }}">
                            Don't have an account?
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</div>
