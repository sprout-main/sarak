<x-guest-layout>
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors">
            <i class="ph ph-arrow-left"></i> Back to login
        </a>
        <span class="text-xs text-zinc-500 font-mono">Reset Password</span>
    </div>

    <div class="mb-4 text-xs text-zinc-400 leading-relaxed">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="e.g. maria@bangued.ph" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-6 pt-4 border-t border-white/10 text-center text-xs text-zinc-400">
        <span>Remember your password?</span>
        <a href="{{ route('login') }}" class="font-semibold text-emerald-400 hover:text-emerald-300 ml-1 hover:underline">
            Back to login
        </a>
    </div>
</x-guest-layout>
