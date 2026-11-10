<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Profile settings')]
    class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /** Aktywna zakładka: account (domyślna) albo team. Zapamiętana w adresie (?tab=team). */
    #[Url(except: 'account')]
    public string $tab = 'account';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('ui-toast', text: __('Profile updated.'), variant: 'success');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        // Nowy kod potwierdzający i przejście do strony, na której się go wpisuje.
        $user->sendEmailVerificationNotification();

        $this->redirect(route('verification.notice'), navigate: false);
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && !Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return !Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your account details and your team')">
        {{-- Zakładki --}}
        <x-ui.segmented wire:model.live="tab" class="mb-5" :options="['account' => __('Account'), 'team' => __('Team')]" />

        @if ($tab === 'team')
            {{-- Zakładka: Drużyna (osobny komponent z własnym formularzem) --}}
            <livewire:pages::settings.team />
        @else
            {{-- Zakładka: Konto --}}
            <form wire:submit="updateProfileInformation" class="w-full space-y-5">
                <x-ui.input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <x-ui.input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <x-ui.text class="mt-3">
                            {{ __('Your email address is unverified.') }}
                            <button type="button" class="font-semibold text-club underline underline-offset-2" wire:click.prevent="resendVerificationNotification">
                                {{ __('Send a code and confirm it.') }}
                            </button>
                        </x-ui.text>

                        @if (session('status') === 'verification-link-sent')
                            <x-ui.text class="mt-2 font-medium text-good">{{ __('A new verification link has been sent to your email address.') }}</x-ui.text>
                        @endif
                    @endif
                </div>

                <x-ui.btn variant="primary" type="submit" data-test="update-profile-button">{{ __('Save') }}</x-ui.btn>
            </form>

            @if ($this->showDeleteUser)
                <livewire:pages::settings.delete-user-form />
            @endif
        @endif
    </x-pages::settings.layout>
</section>
