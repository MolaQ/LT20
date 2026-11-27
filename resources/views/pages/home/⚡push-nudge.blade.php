<?php

use Livewire\Component;

/**
 * Zachęta na stronie głównej (27.11.2026): „Nie przegap kolejki”. Gracz bez powiadomień w tej przeglądarce włącza je
 * jednym kliknięciem; na iPhonie poza zainstalowaną aplikacją widzi, jak dodać stronę do ekranu (push działa tylko tam).
 * Na Androidzie i komputerze dodatkowo przycisk instalacji aplikacji. Zamknięcie chowa baner na 14 dni (localStorage).
 * Pełne ustawienia: pages::settings.notifications.
 */
new class extends Component {
    /** Subskrypcja z przeglądarki (PushSubscription.toJSON), jak w ustawieniach powiadomień. */
    public function save(array $subscription): void
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        abort_unless(auth()->check() && str_starts_with($endpoint, 'https://') && strlen($endpoint) <= 500, 422);

        auth()->user()->updatePushSubscription(
            $endpoint,
            $subscription['keys']['p256dh'] ?? null,
            $subscription['keys']['auth'] ?? null,
            'aes128gcm',
        );

        $this->dispatch('ui-toast', text: __('Notifications are on in this browser.'), variant: 'success');
    }

    public function remove(string $endpoint): void
    {
        auth()->user()?->deletePushSubscription($endpoint);
    }
}; ?>

<div x-data="uiPush(@js((string) config('webpush.vapid.public_key')))">
    <div x-data="{
            hidden: (() => { try { return Number(localStorage.getItem('lt-push-nudge') || 0) > Date.now(); } catch (e) { return false; } })(),
            dismiss() { this.hidden = true; try { localStorage.setItem('lt-push-nudge', String(Date.now() + 14 * 864e5)); } catch (e) {} },
        }">
        <div x-data="uiInstall" x-show="!hidden && !subscribed && (ios || (supported && permission !== 'denied'))" x-cloak x-transition
            class="relative flex flex-col gap-3 overflow-hidden rounded-2xl border border-ui-line bg-ui-surface p-4 pe-10 sm:flex-row sm:items-center">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-club text-club-ink">
                <x-ui.icon name="bell" class="size-6" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="font-display font-extrabold">{{ __('Never miss a matchday') }}</div>
                {{-- iPhone poza aplikacją: najpierw dodanie do ekranu --}}
                <p x-show="ios" class="text-sm text-ui-muted">
                    {{ __('On iPhone notifications work in the app: tap Share, then „Add to Home Screen”, open #LechTYPER from the icon and turn notifications on.') }}
                </p>
                <p x-show="!ios" class="text-sm text-ui-muted">
                    {{ __('Turn on notifications: we remind you the day before and two hours before kick-off if you have not tipped, and send your points after the match.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2" x-show="!ios">
                <x-ui.btn variant="primary" icon="bell" x-on:click="enable()" x-bind:disabled="busy">{{ __('Turn on notifications') }}</x-ui.btn>
                <x-ui.btn variant="subtle" icon="arrow-down-tray" x-show="canInstall" x-cloak x-on:click="install()">{{ __('Install the app') }}</x-ui.btn>
            </div>
            <button type="button" x-on:click="dismiss()" class="absolute end-2 top-2 grid size-8 place-items-center rounded-full text-ui-muted hover:bg-ui-raised hover:text-ui-ink" aria-label="{{ __('Close') }}">
                <x-ui.icon name="x-mark" variant="micro" />
            </button>
        </div>
    </div>
</div>
