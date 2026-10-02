<x-guest-layout>
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors">
            <i class="ph ph-arrow-left"></i> Back to map
        </a>
        <span class="text-xs text-zinc-500 font-mono">Login</span>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-emerald-500 shadow-sm focus:ring-emerald-500" name="remember">
                <span class="ms-2 text-sm text-zinc-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6">
            @if (Route::has('password.request'))
                <a class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @else
                <span></span>
            @endif

            <x-primary-button class="ms-3 !bg-emerald-400 !text-zinc-950 hover:!bg-emerald-300">
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <div class="mt-6 pt-4 border-t border-white/10 text-center text-xs text-zinc-400">
            Don't have an account? 
            <a href="{{ route('register') }}" class="font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">
                Sign up for free
            </a>
        </div>
    </form>
</x-guest-layout>
