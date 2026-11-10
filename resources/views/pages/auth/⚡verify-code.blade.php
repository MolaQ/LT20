<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Potwierdzenie adresu e-mail 6-cyfrowym kodem z maila (zamiast linku).
 * Kod jest ważny godzinę, po 5 błędnych próbach trzeba odczekać minutę. Kod widzi też admin (Użytkownicy).
 * Na stronie „Potwierdź e-mail” i w pasku ustawień konta; $back = adres po potwierdzeniu (domyślnie poprzedni cel).
 */
new class extends Component {
    public string $code = '';

    public ?string $back = null;

    public bool $compact = false;

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

        if ($this->back) {
            session()->flash('status', 'email-verified');
            $this->redirect($this->back, navigate: true);

            return;
        }

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

{{-- compact: pasek w ustawieniach konta (bez autofocus, pole i przyciski w jednym rzędzie). --}}
<div @class(['flex flex-col', 'gap-6' => ! $compact, 'gap-3' => $compact])>
    <x-ui.text :class="$compact ? '' : 'text-center'">
        @if ($compact)
            <span class="font-semibold text-ui-ink">{{ __('Confirm your email address.') }}</span>
            {{ __('Enter the 6-digit code from the email sent to :email. Until then you cannot tip.', ['email' => auth()->user()->email]) }}
        @else
            {{ __('We have sent a 6-digit code to :email. Enter it below to confirm your address.', ['email' => auth()->user()->email]) }}
        @endif
    </x-ui.text>

    <form wire:submit="verify" @class(['flex gap-3', 'flex-col items-center' => ! $compact, 'flex-wrap items-start' => $compact])>
        {{-- Sześć cyfr, po wpisaniu ostatniej formularz wysyła się sam. --}}
        <label @class(['w-full' => ! $compact, 'w-48' => $compact])>
            <span class="sr-only">{{ __('Code') }}</span>
            <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" @if (! $compact) autofocus @endif
                x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 6); $el.value.length === 6 && $nextTick(() => $el.form.requestSubmit())"
                @class([
                    'w-full rounded-2xl border border-ui-line bg-ui-surface text-center font-display font-extrabold text-ui-ink focus:border-club focus:outline-none focus:ring-2 focus:ring-club/30',
                    'h-14 text-3xl tracking-[.5em]' => ! $compact,
                    'h-10 text-xl tracking-[.4em]' => $compact,
                ])
                placeholder="••••••">
        </label>

        @if ($compact)
            <x-ui.btn variant="primary" type="submit">{{ __('Confirm') }}</x-ui.btn>
            <x-ui.btn variant="ghost" type="button" wire:click="resend">{{ __('Send a new code') }}</x-ui.btn>
        @else
            <x-ui.error name="code" />
            <x-ui.button type="submit">{{ __('Confirm') }}</x-ui.button>
        @endif
    </form>

    @if ($compact)
        <x-ui.error name="code" />
    @endif

    @if ($sent)
        <x-ui.text :class="'font-medium text-good'.($compact ? '' : ' text-center')">{{ __('A new code has been sent.') }}</x-ui.text>
    @endif

    @if (! $compact)
        <div class="flex flex-col items-center gap-2">
            <x-ui.btn variant="ghost" size="sm" wire:click="resend">{{ __('Send a new code') }}</x-ui.btn>
            <x-ui.text size="sm" class="text-center">{{ __('No email? Check the spam folder or ask the admin for the code.') }}</x-ui.text>
        </div>
    @else
        <x-ui.text size="sm">{{ __('No email? Check the spam folder or ask the admin for the code.') }}</x-ui.text>
    @endif
</div>
