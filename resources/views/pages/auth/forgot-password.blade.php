<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <x-ui.input name="email" :label="__('Email address')" type="email" required autofocus placeholder="email@example.com" />

            <x-ui.button type="submit" data-test="email-password-reset-link-button">{{ __('Email password reset link') }}</x-ui.button>
        </form>

        <div class="text-center text-sm text-ui-muted">
            <span>{{ __('Or, return to') }}</span>
            <a class="font-semibold text-club" href="{{ route('login') }}" wire:navigate>{{ __('log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
