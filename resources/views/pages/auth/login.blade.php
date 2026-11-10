<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <x-ui.input name="email" :label="__('Email address')" :value="old('email')" type="email" required autofocus autocomplete="email" placeholder="email@example.com" />

            <div class="relative">
                <x-ui.input name="password" :label="__('Password')" type="password" required autocomplete="current-password" :placeholder="__('Password')" viewable />

                @if (Route::has('password.request'))
                    <a class="absolute end-0 top-0 text-sm font-semibold text-club" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 rounded border-ui-line accent-[var(--ui-club)]">
                {{ __('Remember me') }}
            </label>

            <x-ui.button type="submit" data-test="login-button">{{ __('Log in') }}</x-ui.button>
        </form>

        @if (Route::has('register'))
            <div class="text-center text-sm text-ui-muted">
                <span>{{ __('Don\'t have an account?') }}</span>
                <a class="font-semibold text-club" href="{{ route('register') }}" wire:navigate>{{ __('Sign up') }}</a>
            </div>
        @endif
    </div>
</x-layouts::auth>
