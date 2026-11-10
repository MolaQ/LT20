<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Potwierdzenie adresu e-mail 6-cyfrowym kodem z maila (zamiast linku).
 * Kod jest ważny godzinę, po 5 błędnych próbach trzeba odczekać minutę. Kod widzi też admin (Użytkownicy).
 */
new class extends Component {
    public string $code = '';

    public bool $sent = false;

    public function verify(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('home'), navigate: false);

            return;
        }

        $key = 'verify-code:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        $code = preg_replace('/\D/', '', $this->code);

        if (!$user->verificationCodeMatches($code)) {
            RateLimiter::hit($key, 60);
            $this->code = '';
            $this->addError('code', __('The code is wrong or has expired.'));

            return;
        }

        RateLimiter::clear($key);
        $user->confirmEmail();
        event(new Verified($user));

        $this->redirectIntended(route('home'));
    }

    public function resend(): void
    {
        $key = 'verify-code-send:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('code', __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key, 300);
        auth()->user()->sendEmailVerificationNotification();
        $this->code = '';
        $this->sent = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.text class="text-center">
        {{ __('We have sent a 6-digit code to :email. Enter it below to confirm your address.', ['email' => auth()->user()->email]) }}
    </x-ui.text>

    <form wire:submit="verify" class="flex flex-col items-center gap-4">
        {{-- Sześć cyfr, po wpisaniu ostatniej formularz wysyła się sam. --}}
        <label class="w-full">
            <span class="sr-only">{{ __('Code') }}</span>
            <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 6); $el.value.length === 6 && $nextTick(() => $el.form.requestSubmit())"
                class="h-14 w-full rounded-2xl border border-ui-line bg-ui-surface text-center font-display text-3xl font-extrabold tracking-[.5em] text-ui-ink focus:border-club focus:outline-none focus:ring-2 focus:ring-club/30"
                placeholder="••••••">
        </label>
        <x-ui.error name="code" />

        <x-ui.button type="submit">{{ __('Confirm') }}</x-ui.button>
    </form>

    @if ($sent)
        <x-ui.text class="text-center font-medium text-good">{{ __('A new code has been sent.') }}</x-ui.text>
    @endif

    <div class="flex flex-col items-center gap-2">
        <x-ui.btn variant="ghost" size="sm" wire:click="resend">{{ __('Send a new code') }}</x-ui.btn>
        <x-ui.text size="sm" class="text-center">{{ __('No email? Check the spam folder or ask the admin for the code.') }}</x-ui.text>
    </div>
</div>
