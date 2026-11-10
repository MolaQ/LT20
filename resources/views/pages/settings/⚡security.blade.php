<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Component;

new class extends Component {
    use \App\Concerns\SettingsPage;

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->settingsView('Security settings');
    }

    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';



    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {

    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('ui-toast', text: __('Password updated.'), variant: 'success');
    }


}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :panel="$inPanel" :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="space-y-5">
            <x-ui.input wire:model="current_password" :label="__('Current password')" type="password" required autocomplete="current-password" viewable />
            <x-ui.input wire:model="password" :label="__('New password')" type="password" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />
            <x-ui.input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />

            <x-ui.btn variant="primary" type="submit" data-test="update-password-button">{{ __('Save') }}</x-ui.btn>
        </form>
    </x-pages::settings.layout>
</section>
