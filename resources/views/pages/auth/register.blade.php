<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details and your team below')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
            @csrf

            <x-ui.input name="name" :label="__('Name')" :value="old('name')" type="text" required autofocus autocomplete="name" :placeholder="__('Full name')" />
            <x-ui.input name="email" :label="__('Email address')" :value="old('email')" type="email" required autocomplete="email" placeholder="email@example.com" />

            <div class="flex items-center gap-3 text-xs font-bold uppercase tracking-wider text-ui-muted">
                <span class="h-px flex-1 bg-ui-line"></span>{{ __('Your team') }}<span class="h-px flex-1 bg-ui-line"></span>
            </div>

            <x-ui.input name="team_name" :label="__('Team name')" :value="old('team_name')" type="text" required minlength="3" maxlength="40" :placeholder="__('Full team name')" />

            <span class="h-px bg-ui-line"></span>

            <x-ui.input name="password" :label="__('Password')" type="password" required autocomplete="new-password" :placeholder="__('Password')" viewable />
            <x-ui.input name="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" :placeholder="__('Confirm password')" viewable />

            <x-ui.button type="submit" data-test="register-button">{{ __('Create account') }}</x-ui.button>
        </form>

        <div class="text-center text-sm text-ui-muted">
            <span>{{ __('Already have an account?') }}</span>
            <a class="font-semibold text-club" href="{{ route('login') }}" wire:navigate>{{ __('Log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
